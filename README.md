# Laravel Test Mail

A mail driver that stores outgoing mail in your database, plus a mailbox at `/test-mail` to read it.

`MAIL_MAILER=log` flattens a message into a log line and throws away the attachments. `MAIL_MAILER=array` forgets everything at the end of the request. This keeps the whole thing — the full MIME, attachments, embedded images, tags, metadata and custom headers — and gives you somewhere to look at it.

```bash
composer require --dev ebbbang/laravel-test-mail
php artisan test-mail:install
php artisan migrate
```

Then set `MAIL_MAILER=database` and send something.

## Requirements

| | |
|---|---|
| PHP | 8.2+ |
| Laravel | 12, 13 (11 best-effort) |

The test matrix covers PHP 8.2–8.5 against Laravel 11, 12 and 13.

**On Laravel 11:** the code works, but the branch reached end of life on 12 March 2026 and every stable `11.x` release is now flagged by unpatched security advisories. Composer blocks those by default, so an install there resolves to the `11.x-dev` branch rather than a tagged release. It is tested and supported on a best-effort basis; Laravel 12 or 13 is the real recommendation.

## What it captures

Everything Laravel's mail layer can produce, because it hooks in as a Symfony transport rather than an event listener — it sees the finished message:

- HTML and text bodies, from views, markdown, `htmlString` or `Mail::raw()`
- Attachments via `attach()`, `attachData()`, `fromStorage()` and the rest, byte for byte
- Inline images from `$message->embed()`, kept separate from real attachments
- `Cc`, `Bcc`, `Reply-To`, and the envelope recipients actually delivered to
- Tags and metadata (`TagHeader` / `MetadataHeader`)
- Custom headers set through `withSymfonyMessage()`
- Queued mailables, multiple mailers, `Mail::alwaysTo()`

## The mailbox

`/test-mail` gives you a two-pane reader: search, filter by mailer, and per-message HTML / text / attachments / headers / raw tabs. Light, dark and system themes. No build step, no npm, no published assets — the styles are inlined, so it works offline and behind a strict CSP.

Every message can be exported as **`.eml`** (opens in Mail.app, Thunderbird, Outlook) or as a standalone **`.html`** file with embedded images rewritten to `data:` URIs so it renders anywhere.

### Access control

The package ships **no login page**. Access is decided in three escalating steps:

1. **Nothing configured** — the mailbox is reachable in the `local` environment only.
2. **A gate** — define `viewTestMail` and it takes over completely:

   ```php
   Gate::define('viewTestMail', fn (?User $user) => $user?->isAdmin());
   ```

   Type the parameter as nullable and guests are evaluated too, which is what lets this work without auth middleware.

3. **A callback** — for anything the gate can't express:

   ```php
   TestMail::auth(fn (Request $request) => $request->ip() === '10.0.0.1');
   ```

To put the mailbox behind your app's existing login, add `auth` to the middleware stack and your own login flow handles the redirect:

```php
'middleware' => ['web', 'auth', Authorize::class],
```

### Rendering untrusted mail safely

Email HTML is attacker-controlled as far as your app is concerned. The preview is served from its own route into an `<iframe sandbox>` with neither `allow-scripts` nor `allow-same-origin`, putting it in an opaque origin that cannot run scripts, reach the parent page, or touch cookies, under a `default-src 'none'` CSP.

Attachments are always sent as `application/octet-stream` with `Content-Disposition: attachment` — never their own MIME type, or an emailed `.svg` or `.html` would execute on your origin.

## Production

Disabled in production by default. The `database` transport refuses to be constructed and the routes are never registered, so `/test-mail` 404s rather than 403s.

If you select the mailer anyway, it **throws** rather than silently accepting the message — quietly swallowing production mail while looking like successful delivery is the worst possible failure here.

To use it in production regardless, opt in explicitly:

```dotenv
TEST_MAIL_ENABLED=true
```

## Pruning

```bash
php artisan test-mail:prune              # uses test-mail.prune.retention_days (default 7)
php artisan test-mail:prune --days=30
php artisan test-mail:prune --hours=6
php artisan test-mail:prune --pretend
php artisan test-mail:clear              # everything, including stored files
```

Deletes go through model events so the raw `.eml` and attachment blobs are removed with the row — a mass delete would orphan every file on disk. The model is `Prunable`, so `php artisan model:prune --model="Ebbbang\TestMail\Models\TestMailMessage"` works too.

Schedule it yourself, or set `test-mail.prune.schedule` to a cron expression or frequency name (`daily`, `hourly`) and the package registers it for you.

## Configuration

```bash
php artisan vendor:publish --tag=test-mail-config
```

| Key | Default | |
|---|---|---|
| `enabled` | not production | Master switch, `TEST_MAIL_ENABLED` |
| `path` | `test-mail` | Mailbox URL |
| `domain` | `null` | Serve the mailbox on its own domain |
| `middleware` | `['web', Authorize::class]` | Add `auth` to require login |
| `storage.disk` | `local` | Where `.eml` and attachments go |
| `storage.max_attachment_size` | `null` | Skip storing bytes above this size |
| `database.connection` | default | Keep captured mail off your main database |
| `forward` | `null` | Also deliver via another mailer after capturing |
| `prune.retention_days` | `7` | |
| `ui.per_page` / `ui.poll_interval` | `25` / `5` | |

Message metadata lives in the database so the list stays fast; raw MIME and attachment bytes go to a disk, so the table stays small even with large attachments.

### Laravel Cloud and other ephemeral platforms

The package works on Laravel Cloud, but **you must set `TEST_MAIL_DISK`** — the default `local` disk is the wrong choice there. Laravel Cloud's docs are explicit that environment filesystems are

> ephemeral […] each replica of your compute cluster has its own filesystem. Thus, you should treat the filesystem as temporary, unshared disk space that is only consistent during a single request or job.

Message metadata and bodies live in the database, so those survive fine. The raw `.eml` and attachment bytes do not: they vanish on redeploy, and a message captured on one replica is unreadable from another. The mailbox stays usable, but `.eml` export, attachment downloads and embedded images break — intermittently, which is worse than breaking outright.

Point the disk at persistent object storage and set the master switch, since Cloud environments carry `APP_ENV=production` even when they are really staging:

```dotenv
MAIL_MAILER=database
TEST_MAIL_DISK=<your object storage disk>
TEST_MAIL_ENABLED=true
```

If blobs do go missing, the mailbox says so explicitly rather than quietly hiding the download button — and it distinguishes a file that vanished from one deliberately skipped by `storage.max_attachment_size`.

The same applies to any ephemeral or multi-replica setup: containers without a shared volume, autoscaling groups, `/tmp`-backed disks.

### Capture and still deliver

Set `forward` to another configured mailer and messages are stored *and* sent on — useful on staging:

```dotenv
MAIL_MAILER=database
TEST_MAIL_FORWARD=smtp
```

## Events

```php
use Ebbbang\TestMail\Events\MessageStored;

Event::listen(function (MessageStored $event) {
    $event->message;      // the stored TestMailMessage
    $event->sentMessage;  // Symfony's SentMessage
});
```

Laravel's own `MessageSent` carries no reference to the stored row, so this is how you get hold of it.

## Two things worth knowing

**BCC is not in the `.eml`.** Symfony strips the `Bcc` header when rendering a message, because it must never travel with it. The recipients are recorded in the database and shown in the mailbox, but the exported file will not contain them. That is correct MIME behaviour, not a bug.

**`Mail::fake()` bypasses this entirely.** It replaces the mail manager, so no transport runs. Use `Mail::assertSent()` for those tests; use this package when you want to *look* at what was sent.

## Development

```bash
composer test         # parallel, via paratest
composer test:serial
composer lint         # rector, then pint
composer lint:check
composer serve        # workbench demo app at /test-mail, /send, /send-plain
```

## License

MIT
