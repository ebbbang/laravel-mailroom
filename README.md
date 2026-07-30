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

### Attachment previews

Click an attachment to open it in a lightbox — arrow keys to move between them, `Esc` to close, and the open preview stays in the URL (`#preview-2`) so it survives a refresh and can be pasted to someone else.

| Previewed inline | Download only |
|---|---|
| PNG, JPEG, GIF, WebP, AVIF, BMP, ICO, SVG | Office: `.docx`, `.xlsx`, `.pptx` |
| PDF | Archives: `.zip`, `.7z` |
| MP3, WAV, OGG, M4A, AAC, FLAC | TIFF, HEIC |
| MP4, WebM, OGV, MOV | anything unrecognised |
| Text, Markdown, CSV/TSV (as a table), JSON (pretty-printed) | |
| HTML, XML, YAML, JS, CSS, SQL (as **source**) | |
| `.ics` invites (parsed), `.eml` messages (headers + body) | |

**Office documents genuinely cannot be previewed here.** Gmail and Outlook render them with server-side viewers — Google Docs Viewer, Office Online — and those services have to fetch the file over the public internet. A dev mailbox on `127.0.0.1` behind an auth gate is unreachable to them, and the self-hosted alternative is a multi-megabyte JS bundle this package has no build step for. Rather than half-render them, the mailbox says "no preview for .docx" and offers the download.

TIFF and HEIC are left out for a smaller reason: Safari draws them and Chrome does not, so a preview would work on one machine and look broken on another.

Media previews support HTTP Range, so seeking in audio and video works.

#### How previewing stays safe

Serving an attachment with its own MIME type is how stored XSS happens, so the preview route is separate from the download route (which still forces `application/octet-stream`) and is hardened on its own terms:

- **The content type comes from an allowlist, never from the attachment.** `mime_type` is filled in by whoever composed the mail, so it is treated as a lookup key, not a value. It can only ever select a known-safe entry or be refused.
- **Anything text-shaped is never served at all.** It is read and escaped into the page server-side, so an emailed `.html` or `.js` is shown as source and has no content type to be mis-interpreted through.
- **SVG is only ever rendered through `<img>`**, where browsers permit neither scripts nor external fetches, and the response additionally carries `Content-Security-Policy: sandbox` — no `allow-scripts`, no `allow-same-origin` — which covers someone opening the URL directly.
- `X-Content-Type-Options: nosniff` and `Cross-Origin-Resource-Policy: same-origin` on every preview response.

**PDF is a deliberate exception to the sandbox.** Chrome renders PDFs by loading its viewer extension in a cross-process iframe, and a `sandbox` directive on the response blocks that extension's own scripts, so the PDF does not render at all ([crbug.com/413851](https://bugs.chromium.org/p/chromium/issues/detail?id=413851)). Little is given up: a page-level CSP never governed PDF JavaScript in the first place — that runs inside PDFium's sandbox, where it cannot reach the page's DOM, cookies or storage. The allowlisted type, `nosniff` and CORP still apply. PDFs use `<object>` rather than `<iframe>` because Safari has never rendered a PDF in an iframe.

Set `preview.enabled` to `false` to switch all of this off and go back to plain downloads.

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
| `preview.enabled` | `true` | Attachment previews, `TEST_MAIL_PREVIEW` |
| `preview.max_inline_bytes` | `512 KB` | Cap on text-shaped previews |
| `preview.max_csv_rows` | `200` | Rows shown before truncating a table |

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

### Laravel Octane

Supported, and tested against a sandbox modelled on Octane's own
`CurrentApplication::set()`.

The transport is built from whichever container resolved the mail manager, not
from the one the service provider booted with. Under Octane those differ:
providers boot against the base application while each request runs in a
sandbox clone, and `mail.manager` is not one of Octane's warmed services, so it
is rebuilt per request. Following it into the sandbox is what keeps
`MessageStored` firing on the same dispatcher as Laravel's own `MessageSent`.

Nothing in the package holds per-request state between requests. The one piece
of state that deliberately outlives a request is the `TestMail::auth()`
callback, which is set once from a service provider — the same lifetime a
provider has under Octane. Two things follow:

- Do not capture a request, a user, or `$this` inside that closure. It receives
  the current request as its argument; use that.
- `TestMail::flushState()` clears it, if a worker ever needs resetting.

Attachment bytes are read into memory to be written to the disk, which is a
transient spike but a spike nonetheless on long-lived workers. If your
application mails large files, set `storage.max_attachment_size` so oversized
parts are recorded without their payloads.

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
composer serve        # workbench demo at /test-mail, /send, /send-plain, /send-attachments
```

## License

MIT
