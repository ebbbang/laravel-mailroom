<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Storage\RawMessageStore;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Database\Events\DatabaseRefreshed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

/**
 * `migrate:fresh` drops tables as raw DDL, so the model events that normally
 * delete a message's blobs alongside its row never fire. Laravel dispatches
 * DatabaseRefreshed at that point, which is the only hook available -- once
 * the rows are gone the files are unreachable, since pruning works from rows.
 */
class DatabaseRefreshTest extends TestCase
{
    protected function captureWithAttachment(string $order = 'A-1001'): MailroomMessage
    {
        Mail::to('rachel@example.test')->send(
            (new OrderShipped($order))->attachData('payload', 'file.txt', ['mime' => 'text/plain'])
        );

        return MailroomMessage::query()->latest('id')->firstOrFail();
    }

    protected function storageRoot(): string
    {
        return trim((string) config('mailroom.storage.path', 'mailroom'), '/');
    }

    #[Test]
    public function refreshing_the_database_reclaims_the_orphaned_blobs(): void
    {
        $message = $this->captureWithAttachment();

        Storage::disk('local')->assertExists($message->raw_path);

        Event::dispatch(new DatabaseRefreshed);

        Storage::disk('local')->assertMissing($message->raw_path);
        $this->assertEmpty(Storage::disk('local')->allFiles($this->storageRoot()));
    }

    #[Test]
    public function it_leaves_the_blobs_alone_when_a_different_connection_was_refreshed(): void
    {
        /*
         * The data-loss guard. A consumer can keep captured mail on its own
         * connection, and a `migrate:fresh` on the default one then leaves the
         * mailroom rows intact. Flushing here would delete the blobs belonging
         * to rows that still exist.
         *
         * Captured first, then the connection is renamed: the guard is a
         * comparison of connection names, and nothing queries the mailroom
         * connection while the event is handled, so it does not need to be a
         * working connection for this to be a faithful test.
         */
        $message = $this->captureWithAttachment();

        config()->set('mailroom.database.connection', 'mailroom_store');

        Event::dispatch(new DatabaseRefreshed('testing'));

        Storage::disk('local')->assertExists($message->raw_path);
    }

    #[Test]
    public function it_reclaims_blobs_when_the_mailroom_connection_is_the_one_refreshed(): void
    {
        config()->set('mailroom.database.connection', 'testing');

        $message = $this->captureWithAttachment();

        Event::dispatch(new DatabaseRefreshed('testing'));

        Storage::disk('local')->assertMissing($message->raw_path);
    }

    #[Test]
    public function null_on_either_side_resolves_to_the_default_connection(): void
    {
        // `migrate:fresh` without --database reports null, and a consumer who
        // never set mailroom.database.connection is also null. Both mean the
        // default, so they must be treated as a match rather than compared raw.
        // Array form keeps the null explicit; passing it positionally is the
        // Config::set default, so rector strips it and the test stops showing
        // the case it exists to cover.
        config()->set(['mailroom.database.connection' => null]);

        $message = $this->captureWithAttachment();

        Event::dispatch(new DatabaseRefreshed(config('database.default')));

        Storage::disk('local')->assertMissing($message->raw_path);
    }

    #[Test]
    public function an_ordinary_migrate_leaves_the_stored_files_alone(): void
    {
        // Only a refresh wipes tables. Hooking anything broader -- the
        // migration events, say -- would delete captured mail every time a
        // new migration ran.
        $message = $this->captureWithAttachment();

        $this->artisan('migrate')->assertSuccessful();

        Storage::disk('local')->assertExists($message->raw_path);
    }

    #[Test]
    public function it_does_nothing_while_the_package_is_disabled(): void
    {
        $message = $this->captureWithAttachment();

        config()->set('mailroom.enabled', false);

        Event::dispatch(new DatabaseRefreshed);

        Storage::disk('local')->assertExists($message->raw_path);
    }

    #[Test]
    public function clearing_reports_the_stored_files_even_with_no_rows_left(): void
    {
        $message = $this->captureWithAttachment();

        // Exactly the state a `migrate:fresh` leaves behind on a release
        // without the listener: no rows, blobs still on disk.
        MailroomMessage::query()->delete();

        Storage::disk('local')->assertExists($message->raw_path);

        $this->artisan('mailroom:clear', ['--force' => true])
            ->expectsOutputToContain('Stored files emptied')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing($message->raw_path);
    }

    #[Test]
    public function clearing_reports_both_the_row_count_and_the_files(): void
    {
        $this->captureWithAttachment();

        $this->artisan('mailroom:clear', ['--force' => true])
            ->expectsOutputToContain('Cleared 1 captured message(s)')
            ->assertSuccessful();
    }

    #[Test]
    public function the_store_is_resolvable_for_the_listener(): void
    {
        // The listener is constructor-injected, so a binding change would
        // surface here rather than as a mystery failure during migrate:fresh.
        $this->assertInstanceOf(RawMessageStore::class, resolve(RawMessageStore::class));
    }
}
