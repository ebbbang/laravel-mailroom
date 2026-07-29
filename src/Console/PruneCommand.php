<?php

namespace Ebbbang\TestMail\Console;

use Ebbbang\TestMail\Models\TestMailMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneCommand extends Command
{
    protected $signature = 'test-mail:prune
                            {--days= : Delete messages captured more than this many days ago}
                            {--hours= : Delete messages captured more than this many hours ago}
                            {--pretend : Report what would be deleted without deleting it}';

    protected $description = 'Delete captured mail older than the configured retention period';

    public function handle(): int
    {
        $cutoff = $this->cutoff();

        $query = TestMailMessage::query()->where('created_at', '<=', $cutoff);

        $count = (clone $query)->count();

        if ($this->option('pretend')) {
            $this->components->info(sprintf(
                '%d message(s) captured before %s would be pruned.', $count, $cutoff->toDateTimeString()
            ));

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->components->info('No captured mail is old enough to prune.');

            return self::SUCCESS;
        }

        /*
         * Deleted one model at a time on purpose. Each delete fires the
         * model's deleting hook, which is what removes the raw .eml and any
         * attachment blobs -- a mass delete would leave every one of those
         * files orphaned on disk.
         */
        $deleted = 0;

        $query->orderBy('id')->chunkById(500, function ($messages) use (&$deleted): void {
            foreach ($messages as $message) {
                $message->delete();
                $deleted++;
            }
        });

        $this->components->info(sprintf(
            'Pruned %d message(s) captured before %s.', $deleted, $cutoff->toDateTimeString()
        ));

        return self::SUCCESS;
    }

    protected function cutoff(): Carbon
    {
        if (($hours = $this->option('hours')) !== null) {
            return now()->subHours((int) $hours);
        }

        $days = $this->option('days') ?? config('test-mail.prune.retention_days', 7);

        return now()->subDays((int) $days);
    }
}
