<?php

namespace Ebbbang\Mailroom\Listeners;

use Ebbbang\Mailroom\Mailroom;
use Ebbbang\Mailroom\Storage\RawMessageStore;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Events\DatabaseRefreshed;

/**
 * Reclaim stored blobs when the database is wiped out from under them.
 *
 * `migrate:fresh` drops tables as raw DDL, so no Eloquent model events fire --
 * and model events are what this package relies on to delete a message's
 * `.eml` and attachments alongside its row. Without this listener every blob
 * would be orphaned, and nothing could reclaim it afterwards: `mailroom:prune`
 * works from rows, and the rows are gone.
 *
 * Laravel dispatches DatabaseRefreshed from both `migrate:fresh` and
 * `migrate:refresh`, so one listener covers both.
 */
class FlushStorageOnDatabaseRefresh
{
    public function __construct(
        protected RawMessageStore $store,
        protected Config $config,
    ) {}

    public function handle(DatabaseRefreshed $event): void
    {
        if (! Mailroom::enabled()) {
            return;
        }

        if (! $this->refreshWiped($event->database)) {
            return;
        }

        $this->store->flush();
    }

    /**
     * Did this refresh actually drop the tables holding captured mail?
     *
     * Only if it ran against the connection those tables live on. A consumer
     * can point mailroom.database.connection at a separate connection, and a
     * `migrate:fresh` on the default one then leaves the mailroom rows intact.
     * Flushing there would delete the blobs belonging to rows that still
     * exist, turning housekeeping into data loss -- so the connections have to
     * agree before anything is deleted.
     *
     * Both sides are resolved against database.default, since null means "the
     * default connection" in each case and the strings must be compared after
     * that substitution, not before.
     */
    protected function refreshWiped(?string $refreshed): bool
    {
        $default = $this->config->get('database.default');

        return ($refreshed ?? $default)
            === ($this->config->get('mailroom.database.connection') ?? $default);
    }
}
