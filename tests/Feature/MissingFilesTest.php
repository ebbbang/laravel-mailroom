<?php

namespace Ebbbang\TestMail\Tests\Feature;

use Ebbbang\TestMail\Models\TestMailMessage;
use Ebbbang\TestMail\Tests\Fixtures\OrderShipped;
use Ebbbang\TestMail\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

/**
 * What happens when the database row outlives the blob it points at.
 *
 * This is the normal state of affairs on any platform with an ephemeral or
 * per-replica filesystem -- Laravel Cloud documents its disks as "temporary,
 * unshared disk space that is only consistent during a single request or
 * job" -- so the mailbox has to degrade legibly rather than silently.
 */
class MissingFilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewTestMail', fn (?User $user): bool => true);
    }

    protected function captureWithAttachment(): TestMailMessage
    {
        Mail::to('rachel@example.test')->send(
            (new OrderShipped)->attachData('payload', 'invoice.txt', ['mime' => 'text/plain'])
        );

        return TestMailMessage::sole()->load('attachments');
    }

    #[Test]
    public function it_reports_a_vanished_raw_message_rather_than_pretending_it_is_there(): void
    {
        $message = $this->captureWithAttachment();

        $this->assertTrue($message->hasRaw());
        $this->assertFalse($message->rawIsMissing());

        Storage::disk('local')->delete($message->raw_path);

        $message = $message->fresh()->load('attachments');

        $this->assertFalse($message->hasRaw());
        $this->assertTrue($message->rawIsMissing());
        $this->assertTrue($message->hasMissingFiles());
    }

    #[Test]
    public function it_distinguishes_a_skipped_attachment_from_a_vanished_one(): void
    {
        $message = $this->captureWithAttachment();
        $attachment = $message->attachments()->sole();

        $this->assertFalse($attachment->wasSkipped());
        $this->assertFalse($attachment->isMissing());

        Storage::disk('local')->delete($attachment->path);
        $attachment->refresh();

        // Telling someone their file was too large when it actually
        // evaporated would send them looking in the wrong place entirely.
        $this->assertTrue($attachment->isMissing());
        $this->assertFalse($attachment->wasSkipped());

        config()->set('test-mail.storage.max_attachment_size', 1);

        Mail::to('rachel@example.test')->send(
            (new OrderShipped('A-2'))->attachData('too big', 'big.txt', ['mime' => 'text/plain'])
        );

        $skipped = TestMailMessage::query()->latest('id')->first()->attachments()->sole();

        $this->assertTrue($skipped->wasSkipped());
        $this->assertFalse($skipped->isMissing());
    }

    #[Test]
    public function the_mailbox_explains_the_problem_instead_of_hiding_the_button(): void
    {
        $message = $this->captureWithAttachment();

        Storage::disk('local')->delete($message->raw_path);

        $this->get('/test-mail/'.$message->id)
            ->assertOk()
            ->assertSee('Stored files are missing')
            ->assertSee('TEST_MAIL_DISK');
    }

    #[Test]
    public function the_rest_of_the_message_still_reads_fine_without_its_blobs(): void
    {
        $message = $this->captureWithAttachment();

        Storage::disk('local')->deleteDirectory('test-mail');

        // Metadata and bodies live in the database, so the mailbox stays
        // useful even when every blob is gone.
        $this->get('/test-mail/'.$message->id)
            ->assertOk()
            ->assertSee('Order A-1001 shipped')
            ->assertSee('rachel@example.test');
    }

    #[Test]
    public function downloads_of_vanished_files_404_rather_than_erroring(): void
    {
        $message = $this->captureWithAttachment();
        $attachment = $message->attachments()->sole();

        Storage::disk('local')->deleteDirectory('test-mail');

        $this->get('/test-mail/'.$message->id.'/download/eml')->assertNotFound();
        $this->get('/test-mail/'.$message->id.'/content/raw')->assertNotFound();
        $this->get('/test-mail/'.$message->id.'/attachments/'.$attachment->id)->assertNotFound();
    }

    #[Test]
    public function deleting_a_message_whose_files_are_already_gone_does_not_blow_up(): void
    {
        $message = $this->captureWithAttachment();

        Storage::disk('local')->deleteDirectory('test-mail');

        $message->delete();

        $this->assertSame(0, TestMailMessage::query()->count());
    }
}
