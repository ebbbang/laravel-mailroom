<?php

namespace Ebbbang\TestMail\Tests\Feature;

use Ebbbang\TestMail\Models\TestMailAttachment;
use Ebbbang\TestMail\Models\TestMailMessage;
use Ebbbang\TestMail\Storage\RawMessageStore;
use Ebbbang\TestMail\Tests\Fixtures\OrderShipped;
use Ebbbang\TestMail\Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class PruneCommandTest extends TestCase
{
    protected function captureAged(int $daysAgo, string $order = 'A-1'): TestMailMessage
    {
        Mail::to('rachel@example.test')->send(
            (new OrderShipped($order))->attachData('payload', 'file.txt', ['mime' => 'text/plain'])
        );

        $message = TestMailMessage::query()->latest('id')->first();
        $message->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();

        return $message->fresh();
    }

    #[Test]
    public function it_deletes_old_messages_together_with_their_files(): void
    {
        $old = $this->captureAged(30, 'A-OLD');
        $fresh = $this->captureAged(1, 'A-NEW');

        $oldDirectory = resolve(RawMessageStore::class)->directoryFor($old->uuid);

        Storage::disk('local')->assertExists($old->raw_path);

        $this->artisan('test-mail:prune', ['--days' => 7])
            ->expectsOutputToContain('Pruned 1 message')
            ->assertSuccessful();

        $this->assertNull(TestMailMessage::query()->find($old->id));
        $this->assertNotNull(TestMailMessage::query()->find($fresh->id));

        // The whole point of pruning one model at a time: no orphaned blobs.
        Storage::disk('local')->assertMissing($old->raw_path);
        Storage::disk('local')->assertDirectoryEmpty($oldDirectory);
        Storage::disk('local')->assertExists($fresh->raw_path);

        $this->assertSame(0, TestMailAttachment::query()->where('test_mail_message_id', $old->id)->count());
    }

    #[Test]
    public function it_falls_back_to_the_configured_retention_period(): void
    {
        config()->set('test-mail.prune.retention_days', 3);

        $this->captureAged(10);
        $this->captureAged(1);

        $this->artisan('test-mail:prune')->assertSuccessful();

        $this->assertSame(1, TestMailMessage::query()->count());
    }

    #[Test]
    public function it_accepts_an_hours_cutoff(): void
    {
        $recent = $this->captureAged(0);
        $recent->forceFill(['created_at' => now()->subHours(5)])->saveQuietly();

        $this->artisan('test-mail:prune', ['--hours' => 2])->assertSuccessful();

        $this->assertSame(0, TestMailMessage::query()->count());
    }

    #[Test]
    public function pretend_reports_without_deleting(): void
    {
        $this->captureAged(30);

        $this->artisan('test-mail:prune', ['--days' => 7, '--pretend' => true])
            ->expectsOutputToContain('would be pruned')
            ->assertSuccessful();

        $this->assertSame(1, TestMailMessage::query()->count());
    }

    #[Test]
    public function it_reports_when_there_is_nothing_to_prune(): void
    {
        $this->captureAged(1);

        $this->artisan('test-mail:prune', ['--days' => 7])
            ->expectsOutputToContain('No captured mail is old enough')
            ->assertSuccessful();

        $this->assertSame(1, TestMailMessage::query()->count());
    }

    #[Test]
    public function clear_removes_every_message_and_wipes_storage(): void
    {
        $this->captureAged(1, 'A-1');
        $this->captureAged(1, 'A-2');

        $this->artisan('test-mail:clear', ['--force' => true])
            ->expectsOutputToContain('Cleared 2 captured message')
            ->assertSuccessful();

        $this->assertSame(0, TestMailMessage::query()->count());
        $this->assertSame(0, TestMailAttachment::query()->count());
        Storage::disk('local')->assertDirectoryEmpty('test-mail');
    }

    #[Test]
    public function the_model_is_prunable_so_the_frameworks_own_command_works_too(): void
    {
        config()->set('test-mail.prune.retention_days', 7);

        $this->captureAged(30);
        $this->captureAged(1);

        $this->artisan('model:prune', ['--model' => [TestMailMessage::class]])->assertSuccessful();

        $this->assertSame(1, TestMailMessage::query()->count());
    }
}
