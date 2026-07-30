<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Models\MailroomAttachment;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Storage\RawMessageStore;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class PruneCommandTest extends TestCase
{
    protected function captureAged(int $daysAgo, string $order = 'A-1'): MailroomMessage
    {
        Mail::to('rachel@example.test')->send(
            (new OrderShipped($order))->attachData('payload', 'file.txt', ['mime' => 'text/plain'])
        );

        $message = MailroomMessage::query()->latest('id')->first();
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

        $this->artisan('mailroom:prune', ['--days' => 7])
            ->expectsOutputToContain('Pruned 1 message')
            ->assertSuccessful();

        $this->assertNull(MailroomMessage::query()->find($old->id));
        $this->assertNotNull(MailroomMessage::query()->find($fresh->id));

        // The whole point of pruning one model at a time: no orphaned blobs.
        Storage::disk('local')->assertMissing($old->raw_path);
        Storage::disk('local')->assertDirectoryEmpty($oldDirectory);
        Storage::disk('local')->assertExists($fresh->raw_path);

        $this->assertSame(0, MailroomAttachment::query()->where('mailroom_message_id', $old->id)->count());
    }

    #[Test]
    public function it_falls_back_to_the_configured_retention_period(): void
    {
        config()->set('mailroom.prune.retention_days', 3);

        $this->captureAged(10);
        $this->captureAged(1);

        $this->artisan('mailroom:prune')->assertSuccessful();

        $this->assertSame(1, MailroomMessage::query()->count());
    }

    #[Test]
    public function it_accepts_an_hours_cutoff(): void
    {
        $recent = $this->captureAged(0);
        $recent->forceFill(['created_at' => now()->subHours(5)])->saveQuietly();

        $this->artisan('mailroom:prune', ['--hours' => 2])->assertSuccessful();

        $this->assertSame(0, MailroomMessage::query()->count());
    }

    #[Test]
    public function pretend_reports_without_deleting(): void
    {
        $this->captureAged(30);

        $this->artisan('mailroom:prune', ['--days' => 7, '--pretend' => true])
            ->expectsOutputToContain('would be pruned')
            ->assertSuccessful();

        $this->assertSame(1, MailroomMessage::query()->count());
    }

    #[Test]
    public function it_reports_when_there_is_nothing_to_prune(): void
    {
        $this->captureAged(1);

        $this->artisan('mailroom:prune', ['--days' => 7])
            ->expectsOutputToContain('No captured mail is old enough')
            ->assertSuccessful();

        $this->assertSame(1, MailroomMessage::query()->count());
    }

    #[Test]
    public function clear_removes_every_message_and_wipes_storage(): void
    {
        $this->captureAged(1, 'A-1');
        $this->captureAged(1, 'A-2');

        $this->artisan('mailroom:clear', ['--force' => true])
            ->expectsOutputToContain('Cleared 2 captured message')
            ->assertSuccessful();

        $this->assertSame(0, MailroomMessage::query()->count());
        $this->assertSame(0, MailroomAttachment::query()->count());
        Storage::disk('local')->assertDirectoryEmpty('mailroom');
    }

    #[Test]
    public function the_model_is_prunable_so_the_frameworks_own_command_works_too(): void
    {
        config()->set('mailroom.prune.retention_days', 7);

        $this->captureAged(30);
        $this->captureAged(1);

        $this->artisan('model:prune', ['--model' => [MailroomMessage::class]])->assertSuccessful();

        $this->assertSame(1, MailroomMessage::query()->count());
    }
}
