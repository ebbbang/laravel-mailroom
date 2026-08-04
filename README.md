# Laravel Mailroom

[![Latest version](https://img.shields.io/packagist/v/ebbbang/laravel-mailroom.svg?style=flat-square)](https://packagist.org/packages/ebbbang/laravel-mailroom)
[![Tests](https://img.shields.io/github/actions/workflow/status/ebbbang/laravel-mailroom/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ebbbang/laravel-mailroom/actions/workflows/tests.yml)
[![Downloads](https://img.shields.io/packagist/dt/ebbbang/laravel-mailroom.svg?style=flat-square)](https://packagist.org/packages/ebbbang/laravel-mailroom)
[![License](https://img.shields.io/packagist/l/ebbbang/laravel-mailroom.svg?style=flat-square)](LICENSE)

A mail driver that stores outgoing mail in your database, plus a mailbox at `/mailroom` to read it.

`MAIL_MAILER=log` flattens a message into a log line and throws away the attachments. `MAIL_MAILER=array` forgets everything at the end of the request. Mailroom keeps the whole thing — full MIME, attachments, embedded images, tags, metadata, custom headers — and gives you somewhere to look at it.

![The Mailroom mailbox in light mode](https://raw.githubusercontent.com/ebbbang/laravel-mailroom/main/art/mailbox-light.png)

![The Mailroom mailbox in dark mode](https://raw.githubusercontent.com/ebbbang/laravel-mailroom/main/art/mailbox-dark.png)

## Table of contents

- [Installation](#installation)
- [Requirements](#requirements)
- [What gets captured](#what-gets-captured)
- [The mailbox](#the-mailbox)
- [Access control](#access-control)
- [Staging and QA](#staging-and-qa)
- [Configuration](#configuration)
- [Production](#production)
- [Pruning](#pruning)
- [Advanced](#advanced)
- [Caveats](#caveats)
- [Alternatives](#alternatives)
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security](#security)
- [Credits](#credits)
- [License](#license)

## Installation

```bash
composer require --dev ebbbang/laravel-mailroom
php artisan mailroom:install
```

The installer publishes the config, offers to set `MAIL_MAILER=mailroom` in your `.env`, and offers to run the migrations. Open `/mailroom` and your next email will be there.

**The installer is a convenience, not a requirement.** The service provider is auto-discovered, the config is merged from the package, the migrations are loaded from it, and a `mailroom` mailer is registered for you — so this is equivalent:

```bash
composer require --dev ebbbang/laravel-mailroom
php artisan migrate          # creates the tables straight from the package
echo "MAIL_MAILER=mailroom" >> .env
```

You only need `config/mailroom.php` in your project if you intend to change something in it.

To run unattended — CI, a Dockerfile, a provisioning script — every prompt has a flag:

```bash
php artisan mailroom:install --no-interaction --set-mailer --migrate
php artisan mailroom:install --no-config --no-migrate    # publish nothing, touch nothing
```

> **Pin with `^0.3` while this is 0.x.** Composer treats `^0.3` as `0.3.*` only, so moving to a 0.4 release needs a deliberate bump. Breaking changes may land in minor versions until 1.0.

## Requirements

| | |
|---|---|
| PHP | 8.2+ |
| Laravel | 12, 13 (11 best-effort) |

The test matrix covers PHP 8.2–8.5 against Laravel 11, 12 and 13.

**On Laravel 11** the code works, but the branch reached end of life on 12 March 2026 and every stable `11.x` release is now flagged by unpatched security advisories. Composer blocks those by default, so an install there resolves to the `11.x-dev` branch rather than a tagged release. It is tested and supported on a best-effort basis; Laravel 12 or 13 is the real recommendation.

## What gets captured

Everything Laravel's mail layer can produce. Mailroom hooks in as a Symfony transport rather than an event listener, so it sees the finished message:

- HTML and text bodies, from views, markdown, `htmlString` or `Mail::raw()`
- Attachments via `attach()`, `attachData()`, `fromStorage()` and the rest, byte for byte
- Inline images from `$message->embed()`, kept separate from real attachments
- `Cc`, `Bcc`, `Reply-To`, and the envelope recipients actually delivered to
- Tags and metadata (`TagHeader` / `MetadataHeader`)
- Custom headers set through `withSymfonyMessage()`
- Queued mailables, multiple mailers, `Mail::alwaysTo()`

## The mailbox

`/mailroom` is a two-pane reader: search, filter by mailer, and per-message HTML / text / attachments / headers / raw tabs. Light, dark and system themes.

No build step, no npm, no published assets — the styles are inlined, so it works offline and behind a strict CSP.

### Exporting

Every message can be exported as:

- **`.eml`** — opens in Mail.app, Thunderbird and Outlook
- **`.html`** — a standalone file with embedded images rewritten to `data:` URIs, so it renders anywhere

### Attachment previews

Click an attachment to open it in a lightbox. Arrow keys move between attachments, `Esc` closes, clicking outside dismisses, and the open preview stays in the URL (`#preview-2`) so it survives a refresh and can be pasted to someone else.

| Previewed inline | Download only |
|---|---|
| PNG, JPEG, GIF, WebP, AVIF, BMP, ICO, SVG | Office: `.docx`, `.xlsx`, `.pptx` |
| PDF | Archives: `.zip`, `.7z` |
| MP3, WAV, OGG, M4A, AAC, FLAC | TIFF, HEIC |
| MP4, WebM, OGV, MOV | anything unrecognised |
| Text, Markdown, CSV/TSV (as a table), JSON (pretty-printed) | |
| HTML, XML, YAML, JS, CSS, SQL (as **source**) | |
| `.ics` invites (parsed), `.eml` messages (headers + body) | |

Media previews support HTTP Range, so seeking in audio and video works.

**Office documents genuinely cannot be previewed here.** Gmail and Outlook render them with server-side viewers — Google Docs Viewer, Office Online — and those services must fetch the file over the public internet. A dev mailbox on `127.0.0.1` behind an auth gate is unreachable to them. The self-hosted alternative is a multi-megabyte JS bundle this package has no build step for, so rather than half-render them the mailbox says "no preview for .docx" and offers the download.

TIFF and HEIC are left out for a smaller reason: Safari draws them and Chrome does not, so a preview would work on one machine and look broken on another.

Set `preview.enabled` to `false` to switch previews off and go back to plain downloads.

#### How previewing stays safe

Serving an attachment under its own MIME type is how stored XSS happens, so the preview route is separate from the download route and hardened on its own terms:

- The content type comes from an **allowlist**, never from the attachment's `mime_type`, which is untrusted input.
- Anything text-shaped is **never served at all** — it is escaped into the page server-side, so an emailed `.html` or `.js` is shown as source.
- SVG renders only through `<img>`, under a `Content-Security-Policy: sandbox` response header.
- Downloads are always `application/octet-stream` with `Content-Disposition: attachment`.

PDF is a deliberate, documented exception to the sandbox. [SECURITY.md](https://github.com/ebbbang/laravel-mailroom/blob/main/SECURITY.md) has the full threat model and the reasoning.

## Access control

Mailroom ships **no login page**. Access is decided in three escalating steps.

**1. Nothing configured** — the mailbox is reachable in the `local` environment only.

**2. A gate** — define `viewMailroom` and it takes over completely:

```php
Gate::define('viewMailroom', fn (?User $user) => $user?->isAdmin());
```

Type the parameter as nullable and guests are evaluated too, which is what lets this work without auth middleware.

**3. A callback** — for anything a gate cannot express:

```php
Mailroom::auth(fn (Request $request) => $request->ip() === '10.0.0.1');
```

To put the mailbox behind your application's existing login, add `auth` to the middleware stack and your own login flow handles the redirect:

```php
'middleware' => ['web', 'auth', Authorize::class],
```

### Rendering untrusted mail safely

Email HTML is attacker-controlled as far as your app is concerned. Message bodies are served from their own route into an `<iframe sandbox>` with neither `allow-scripts` nor `allow-same-origin`, putting them in an opaque origin that cannot run scripts, reach the parent page, or touch cookies, under a `default-src 'none'` CSP.

## Staging and QA

Mailroom is as useful on a shared staging environment as it is on your laptop. Testers get a mailbox they can read in the browser — no third-party catcher to sign up for, no inbox credentials to share around, and the mail stays inside your own infrastructure.

Captured mail stays captured, so your staging fixtures can use whatever addresses make the test realistic.

```dotenv
MAIL_MAILER=mailroom
MAILROOM_ENABLED=true
MAILROOM_DISK=<your object storage disk>
```

Three things to get right:

**Enable it explicitly.** Most staging platforms — Laravel Cloud, Forge, Heroku-style hosts — set `APP_ENV=production` even when the environment is really staging, and Mailroom stays off in production unless you say otherwise. That default is deliberate: see [Production](#production).

**Put it behind your login.** The mailbox holds the full contents of every email your app sends, password reset links included. On anything reachable from the internet, add `auth` to the middleware stack and gate it:

```php
'middleware' => ['web', 'auth', Authorize::class],

Gate::define('viewMailroom', fn (?User $user) => $user?->isQaTeam());
```

**Point the disk at persistent storage.** Staging platforms usually run ephemeral, multi-replica filesystems, where the default `local` disk loses attachments between deploys and between replicas. [Laravel Cloud and other ephemeral platforms](#laravel-cloud-and-other-ephemeral-platforms) covers this.

Set a short `prune.retention_days` while you are there, so a long-lived staging box does not accumulate months of real-looking personal data.

## Configuration

Defaults are merged from the package, so everything below works without publishing anything. Publish only what you mean to change:

```bash
php artisan vendor:publish --tag=mailroom-config   # config/mailroom.php
php artisan vendor:publish --tag=mailroom-views    # resources/views/vendor/mailroom
```

**The migrations are deliberately not publishable.** They are loaded from the package only while Mailroom is enabled, which is what stops a production deploy from gaining the tables. A published copy would sit in `database/migrations` and run regardless, quietly removing that guarantee. Table names and the connection are config-driven already, and schema changes belong in your own `ALTER` migration.

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
| `prune.retention_days` | `7` | Default age cutoff for `mailroom:prune` |
| `prune.schedule` | `null` | Cron expression or frequency name to auto-schedule pruning |
| `ui.per_page` | `25` | Messages per page |
| `ui.poll_interval` | `5` | Seconds between new-mail checks; `null` disables polling |
| `preview.enabled` | `true` | Attachment previews |
| `preview.max_inline_bytes` | `512 KB` | Cap on text-shaped previews |
| `preview.max_csv_rows` | `200` | Rows shown before truncating a table |

Every environment variable Mailroom reads:

| Variable | Config key | Default |
|---|---|---|
| `MAILROOM_ENABLED` | `enabled` | `true` outside production |
| `MAILROOM_PATH` | `path` | `mailroom` |
| `MAILROOM_DOMAIN` | `domain` | `null` |
| `MAILROOM_DISK` | `storage.disk` | `local` |
| `MAILROOM_STORAGE_PATH` | `storage.path` | `mailroom` |
| `MAILROOM_DB_CONNECTION` | `database.connection` | your default connection |
| `MAILROOM_RETENTION_DAYS` | `prune.retention_days` | `7` |
| `MAILROOM_PRUNE_SCHEDULE` | `prune.schedule` | `null` |
| `MAILROOM_PREVIEW` | `preview.enabled` | `true` |

Message metadata lives in the database so the list stays fast. Raw MIME and attachment bytes go to a disk, so the table stays small even with large attachments.

## Production

Disabled in production by default. The `mailroom` transport refuses to be constructed and the routes are never registered, so `/mailroom` 404s rather than 403s.

If you select the mailer anyway, it **throws** rather than silently accepting the message. Quietly swallowing production mail while looking like successful delivery is the worst possible failure here.

To use it in production regardless, opt in explicitly:

```dotenv
MAILROOM_ENABLED=true
```

Be deliberate about that. You are storing the full contents of every outgoing email — password reset links included — in your database and on a disk.

## Pruning

```bash
php artisan mailroom:prune              # uses prune.retention_days (default 7)
php artisan mailroom:prune --days=30
php artisan mailroom:prune --hours=6
php artisan mailroom:prune --pretend
php artisan mailroom:clear              # everything, including stored files
```

Deletes go through model events so the raw `.eml` and attachment blobs are removed with the row — a mass delete would orphan every file on disk. The model is `Prunable`, so this works too:

```bash
php artisan model:prune --model="Ebbbang\Mailroom\Models\MailroomMessage"
```

Schedule it yourself, or set `prune.schedule` to a cron expression or frequency name (`daily`, `hourly`) and the package registers it for you.

## Advanced

### Events

```php
use Ebbbang\Mailroom\Events\MessageStored;

Event::listen(function (MessageStored $event) {
    $event->message;      // the stored MailroomMessage
    $event->sentMessage;  // Symfony's SentMessage
});
```

Laravel's own `MessageSent` carries no reference to the stored row, so this is how you get hold of it.

### Laravel Cloud and other ephemeral platforms

Mailroom works on Laravel Cloud, but **you must set `MAILROOM_DISK`** — the default `local` disk is the wrong choice there. Laravel Cloud's docs are explicit that environment filesystems are

> ephemeral […] each replica of your compute cluster has its own filesystem. Thus, you should treat the filesystem as temporary, unshared disk space that is only consistent during a single request or job.

Message metadata and bodies live in the database, so those survive fine. The raw `.eml` and attachment bytes do not: they vanish on redeploy, and a message captured on one replica is unreadable from another. The mailbox stays usable, but `.eml` export, attachment downloads and embedded images break — intermittently, which is worse than breaking outright.

Point the disk at persistent object storage, and set the master switch, since Cloud environments carry `APP_ENV=production` even when they are really staging:

```dotenv
MAIL_MAILER=mailroom
MAILROOM_DISK=<your object storage disk>
MAILROOM_ENABLED=true
```

If blobs do go missing the mailbox says so explicitly, rather than quietly hiding the download button — and it distinguishes a file that vanished from one deliberately skipped by `storage.max_attachment_size`.

The same applies to any ephemeral or multi-replica setup: containers without a shared volume, autoscaling groups, `/tmp`-backed disks.

### Laravel Octane

Supported, and tested against a sandbox modelled on Octane's own `CurrentApplication::set()`.

The transport is built from whichever container resolved the mail manager, not from the one the service provider booted with. Under Octane those differ: providers boot against the base application while each request runs in a sandbox clone, and `mail.manager` is not one of Octane's warmed services, so it is rebuilt per request. Following it into the sandbox is what keeps `MessageStored` firing on the same dispatcher as Laravel's own `MessageSent`.

Nothing in the package holds per-request state between requests. The one piece of state that deliberately outlives a request is the `Mailroom::auth()` callback, set once from a service provider — the same lifetime a provider has under Octane. Two things follow:

- Do not capture a request, a user, or `$this` inside that closure. It receives the current request as its argument; use that.
- `Mailroom::flushState()` clears it, if a worker ever needs resetting.

Attachment bytes are read into memory to be written to the disk. That is a transient spike, but a spike nonetheless on long-lived workers. If your application mails large files, set `storage.max_attachment_size` so oversized parts are recorded without their payloads.

## Caveats

**BCC is not in the `.eml`.** Symfony strips the `Bcc` header when rendering a message, because it must never travel with it. The recipients are recorded in the database and shown in the mailbox, but the exported file will not contain them. That is correct MIME behaviour, not a bug.

**`Mail::fake()` bypasses this entirely.** It replaces the mail manager, so no transport runs. Use `Mail::assertSent()` for those tests; use Mailroom when you want to *look* at what was sent.

## Alternatives

A few packages already log mail to the database — [`shvetsgroup/laravel-email-database-log`](https://packagist.org/packages/shvetsgroup/laravel-email-database-log) and [`stackkit/laravel-database-emails`](https://packagist.org/packages/stackkit/laravel-database-emails) among them — and [`spatie/laravel-database-mail-templates`](https://packagist.org/packages/spatie/laravel-database-mail-templates) stores templates rather than sent mail.

Mailroom is a **development tool** rather than a logging or queueing layer: it exists to be *read*. That means the mailbox UI, attachment previews and `.eml` export, and a package that refuses to run in production unless you explicitly opt in.

If you would rather run a separate service, [Mailpit](https://mailpit.axllent.org) and [Helo](https://usehelo.com) catch SMTP outside your application entirely. Mailroom trades that isolation for needing nothing else installed, and for capturing what Laravel actually built rather than what reached an SMTP socket.

## Testing

```bash
composer test         # parallel, via paratest
composer test:serial
composer lint         # rector, then pint
composer lint:check
```

## Changelog

See [CHANGELOG.md](https://github.com/ebbbang/laravel-mailroom/blob/main/CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](https://github.com/ebbbang/laravel-mailroom/blob/main/CONTRIBUTING.md) — it covers the workbench demo mailbox, which seeds one message per branch of the UI so you can see every state without composing mail by hand.

Please note the [Code of Conduct](https://github.com/ebbbang/laravel-mailroom/blob/main/CODE_OF_CONDUCT.md).

## Security

Please do not open a public issue for a vulnerability. [SECURITY.md](https://github.com/ebbbang/laravel-mailroom/blob/main/SECURITY.md) explains how to report privately, and documents the threat model — the mailbox renders attacker-controlled content by design, and how that is contained is worth reading before reporting.

## Credits

- [Ebrahim Bangdiwala](https://github.com/ebbbang)
- [All contributors](https://github.com/ebbbang/laravel-mailroom/contributors)

## License

MIT. See [LICENSE](LICENSE).
