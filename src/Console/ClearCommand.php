<?php

namespace Ebbbang\Mailroom\Console;

use Ebbbang\Mailroom\Models\MailroomAttachment;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Storage\RawMessageStore;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

class ClearCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'mailroom:clear {--force : Run the command without confirmation}';

    protected $description = 'Delete every captured message and its stored files';

    public function handle(RawMessageStore $store): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $count = MailroomMessage::query()->count();

        /*
         * A mass delete skips model events, so nothing here can rely on the
         * per-model cleanup hook. Attachment rows are removed explicitly (in
         * case the consumer published the migration and dropped the foreign
         * key) and the storage directory is wiped in a single call.
         */
        MailroomAttachment::query()->delete();
        MailroomMessage::query()->delete();
        $store->flush();

        /*
         * Reported separately because the two can legitimately disagree. After
         * a `migrate:fresh` on an older release the rows are gone while the
         * blobs remain, so this command clears "0 messages" and still frees
         * real disk -- and saying only the row count made it look like a no-op.
         */
        $this->components->info($count > 0
            ? sprintf('Cleared %d captured message(s) and their stored files.', $count)
            : 'No captured messages. Stored files emptied.');

        return self::SUCCESS;
    }
}
