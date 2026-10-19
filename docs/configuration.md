# Configuration

Defaults are merged from the package, so everything below works without publishing anything. Publish only what you mean to change. The config, to `config/mailroom.php`:

```bash
php artisan vendor:publish --tag=mailroom-config
```

The views, to `resources/views/vendor/mailroom`:

```bash
php artisan vendor:publish --tag=mailroom-views
```

**The migrations are deliberately not publishable.** They are loaded from the package only while Mailroom is enabled, which is what stops a production deploy from gaining the tables. A published copy would sit in `database/migrations` and run regardless, quietly removing that guarantee. Table names and the connection are config-driven already, and schema changes belong in your own `ALTER` migration.

## Settings

| Key | Default | |
|---|---|---|
| `enabled` | not production | Master switch |
| `path` | `mailroom` | Mailbox URL path |
| `domain` | `null` | Serve the mailbox on its own domain |
| `middleware` | `['web', Authorize::class]` | Add `auth` to require login |
| `storage.disk` | `local` | Where `.eml` and attachment bytes go |
| `storage.path` | `mailroom` | Directory within that disk |
| `storage.max_attachment_size` | `null` | Skip storing bytes above this size, in bytes |
| `database.connection` | default | Keep captured mail off your main database |
| `database.messages_table` | `mailroom_messages` | |
| `database.attachments_table` | `mailroom_attachments` | |
| `database.reads_table` | `mailroom_reads` | |
| `database.addresses_table` | `mailroom_addresses` | Every address on every message, one row per role |
| `forward.mailer` | `null` | Mailer the Forward button sends through; `null` leaves it explaining the setup |
| `forward.require_authenticated_user` | `true` | Outside `local`, forwarding needs a signed-in user |
| `forward.allowed` | `[]` | Addresses or `@domain` entries a forward may target; empty means anywhere |
| `forward.rate_limit` | `10` | Forwards a minute, per user then per IP; `null` removes the limit |
| `spam.driver` | `null` | `postmark` adds the spam check; `null` leaves the pane explaining the setup |
| `spam.endpoint` | Postmark's API | Where a check is posted |
| `spam.require_authenticated_user` | `true` | Outside `local`, checking needs a signed-in user |
| `spam.rate_limit` | `10` | Checks a minute, per user then per IP; `null` removes the limit |
| `spam.timeout` | `15` | Seconds to wait for a score |
| `prune.retention_days` | `7` | Default age cutoff for `mailroom:prune` |
| `prune.schedule` | `null` | Cron expression or frequency name to auto-schedule pruning |
| `ui.per_page` | `25` | Messages per page |
| `ui.poll_interval` | `5` | Seconds between new-mail checks; `null` disables polling |
| `preview.enabled` | `true` | Attachment previews |
| `preview.max_inline_bytes` | `512 KB` | Cap on text-shaped previews |
| `preview.max_csv_rows` | `200` | Rows shown before truncating a table |

## Environment variables

Every environment variable Mailroom reads:

| Variable | Config key | Default |
|---|---|---|
| `MAILROOM_ENABLED` | `enabled` | `true` outside production |
| `MAILROOM_PATH` | `path` | `mailroom` |
| `MAILROOM_DOMAIN` | `domain` | `null` |
| `MAILROOM_DISK` | `storage.disk` | `local` |
| `MAILROOM_STORAGE_PATH` | `storage.path` | `mailroom` |
| `MAILROOM_DB_CONNECTION` | `database.connection` | your default connection |
| `MAILROOM_FORWARD_MAILER` | `forward.mailer` | `null` |
| `MAILROOM_FORWARD_REQUIRE_AUTH` | `forward.require_authenticated_user` | `true` |
| `MAILROOM_FORWARD_ALLOWED` | `forward.allowed` | empty |
| `MAILROOM_FORWARD_RATE_LIMIT` | `forward.rate_limit` | `10` |
| `MAILROOM_SPAM_DRIVER` | `spam.driver` | `null` |
| `MAILROOM_SPAM_ENDPOINT` | `spam.endpoint` | Postmark's SpamCheck API |
| `MAILROOM_SPAM_REQUIRE_AUTH` | `spam.require_authenticated_user` | `true` |
| `MAILROOM_SPAM_RATE_LIMIT` | `spam.rate_limit` | `10` |
| `MAILROOM_SPAM_TIMEOUT` | `spam.timeout` | `15` |
| `MAILROOM_RETENTION_DAYS` | `prune.retention_days` | `7` |
| `MAILROOM_PRUNE_SCHEDULE` | `prune.schedule` | `null` |
| `MAILROOM_PREVIEW` | `preview.enabled` | `true` |

Message metadata lives in the database so the list stays fast. Raw MIME and attachment bytes go to a disk, so the table stays small even with large attachments.
