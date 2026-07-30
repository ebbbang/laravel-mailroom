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

        $this->components->info(sprintf('Cleared %d captured message(s).', $count));

        return self::SUCCESS;
    }
}
