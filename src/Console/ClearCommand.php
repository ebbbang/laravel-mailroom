<?php

namespace Ebbbang\TestMail\Console;

use Ebbbang\TestMail\Models\TestMailAttachment;
use Ebbbang\TestMail\Models\TestMailMessage;
use Ebbbang\TestMail\Storage\RawMessageStore;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

class ClearCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'test-mail:clear {--force : Run the command without confirmation}';

    protected $description = 'Delete every captured message and its stored files';

    public function handle(RawMessageStore $store): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $count = TestMailMessage::query()->count();

        /*
         * A mass delete skips model events, so nothing here can rely on the
         * per-model cleanup hook. Attachment rows are removed explicitly (in
         * case the consumer published the migration and dropped the foreign
         * key) and the storage directory is wiped in a single call.
         */
        TestMailAttachment::query()->delete();
        TestMailMessage::query()->delete();
        $store->flush();

        $this->components->info(sprintf('Cleared %d captured message(s).', $count));

        return self::SUCCESS;
    }
}
