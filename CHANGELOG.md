# Changelog

All notable changes to `ebbbang/laravel-mailroom` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html),
with the caveat that **while the version is below 1.0, breaking changes may
land in minor releases** — see the pinning note in the README.

## [Unreleased]

## [0.4.0] - 2026-08-06

### Added

- A **Forward** button on each message. Set `MAILROOM_FORWARD_MAILER` to a
  mailer that can deliver and a tester can send one message to a real inbox to
  see how it renders in Gmail or Outlook. Nothing is sent without a click, so
  SMTP covers only what someone actually checked.

  Sending to the original recipient replays the stored MIME byte for byte.
  Sending anywhere else rewrites `To`, drops `Cc`, and keeps the originals as
  `X-Mailroom-Original-To` and `X-Mailroom-Original-Cc`. Only the chosen
  address receives a copy, and the stored message is never modified.

  The button is present whether or not forwarding is configured — without a
  mailer it explains how to switch it on, which is how anyone finds out the
  feature exists. The route that does the sending is never registered until you
  configure one.

- `MAILROOM_FORWARD_REQUIRE_AUTH`, on by default. Outside the `local`
  environment a forward needs a signed-in user, so opening the mailbox up with
  a permissive `Mailroom::auth()` callback grants reading without also granting
  the ability to send from your domain.

## [0.3.2] - 2026-08-06

### Fixed

- A database refresh no longer deletes captured mail during a test run.
  Laravel's `RefreshDatabase` runs `migrate:fresh` as it boots each test, which
  fired the storage cleanup added in 0.3.1 — before a test could call
  `Storage::fake()`, so it emptied the real disk. Anyone running their suite
  lost the mail they had captured while developing. The refresh belongs to a
  throwaway test database, so it is no longer acted on while tests are running.

## [0.3.1] - 2026-08-05

### Fixed

- `migrate:fresh` and `migrate:refresh` left every captured message's `.eml`
  and attachments on disk. Wiping your database now takes the stored files with
  it. Upgrading will not reclaim orphans from earlier releases — run
  `php artisan mailroom:clear` once to remove those.
- Storage is emptied only when the refreshed connection is the one Mailroom
  uses, so a `migrate:fresh` elsewhere leaves mail on a separate
  `MAILROOM_DB_CONNECTION` intact. `migrate:rollback` and `migrate:reset` still
  leave files behind.
- `mailroom:clear` reported `Cleared 0 captured message(s)` while deleting
  orphaned files. It now reports the stored files separately from the row count.

## [0.3.0] - 2026-08-04

**Contains a breaking change.** If you set `MAILROOM_FORWARD`, remove it — see
below for why, and for what replaces it.

### Added

- A **Staging and QA** section in the README. Mailroom is as useful on a shared
  staging environment as it is locally: testers read captured mail in the
  browser without a third-party catcher, without shared inbox credentials, and
  without mail leaving your infrastructure. Covers enabling it where the
  platform reports `APP_ENV=production`, gating it behind your own login, and
  pointing the disk at persistent storage.

### Removed

- **`mailroom.forward` and `MAILROOM_FORWARD`.** Naming another mailer there
  relayed every captured message on send, to the message's *original*
  recipients — so pointing it at working SMTP on a staging environment
  delivered test mail to real customers. It also billed an SMTP send for every
  message whether or not anyone wanted it delivered.

  Deliberate, per-message forwarding from the mailbox replaces it in 0.3.1:
  everything is captured, and a message goes out only when someone opens it and
  chooses to send it, which is the point at which you want a real client to
  render it.

  If you relied on capture-and-deliver, keep a second mailer configured and
  select it directly for the mail you want delivered.

### Changed

- The transport is now terminal in all configurations, which removes the
  self-forward and recursion guards it needed to carry.

## [0.2.0] - 2026-08-01

**Contains breaking changes.** Two public surfaces were removed, so this is a
minor rather than a patch release. If you pinned `^0.1`, Composer resolves that
to `0.1.*` and will not pick this up until you bump the constraint deliberately.

### Added

- `mailroom:install` can now run the migrations, so installation is a single
  command. It refuses to do so while the package is disabled, because the
  migrations are not registered in that state and running them would only
  execute the application's unrelated pending migrations.
- Flags for every prompt, so the installer can run unattended:
  `--set-mailer`, `--migrate`, `--no-migrate`, `--no-config`.

### Changed

- The README no longer implies `mailroom:install` is required. It is a
  convenience: the provider is auto-discovered, config is merged, migrations
  are loaded from the package, and the `mailroom` mailer is registered, so
  `composer require` plus `migrate` plus `MAIL_MAILER=mailroom` is enough.

### Removed

- The `mailroom-migrations` publish tag. Publishing put a copy in
  `database/migrations` that ran regardless of `MAILROOM_ENABLED`, defeating
  the guard that keeps a production deploy from gaining the tables. Table
  names and the connection are already config-driven; schema changes belong
  in your own `ALTER` migration.
- The global `Mailroom` class alias. It was never a facade, and the class is
  imported normally.

## [0.1.0] - 2026-07-30

First release.

### Added

- A `mailroom` mail transport that captures every outgoing message: HTML and
  text bodies, attachments, inline images, tags, metadata and custom headers.
  It hooks in as a Symfony transport, so it sees the finished message rather
  than reconstructing one.
- An auth-gated mailbox at `/mailroom` with search, a per-mailer filter,
  pagination, and light, dark and system themes. No build step and no
  published assets — the styles are inlined.
- Attachment previews in a lightbox: images, SVG, PDF, audio, video, text,
  Markdown, CSV and TSV as tables, pretty-printed JSON, source files, `.ics`
  invites and nested `.eml` messages. Media supports HTTP Range, so seeking
  works. Office documents and archives are download-only.
- Export as `.eml`, or as a standalone `.html` file with embedded images
  rewritten to `data:` URIs.
- Three levels of access control: local-only by default, a `viewMailroom`
  gate, or a `Mailroom::auth()` callback. The package ships no login page and
  defers to the host application's.
- `mailroom:install`, `mailroom:prune` and `mailroom:clear` commands. The
  message model is `Prunable`, so `model:prune` works too.
- A `MessageStored` event carrying the persisted row, which Laravel's own
  `MessageSent` does not provide.
- Optional forwarding, so a captured message can also be delivered through
  another mailer.

### Security

- Disabled in production by default. The transport throws rather than
  silently accepting mail, and the mailbox routes are never registered.
- Attachment previews serve a content type taken from an allowlist, never the
  attachment's own. Anything text-shaped, `.html` included, is escaped into
  the page rather than served, and SVG is rendered only through `<img>` under
  a `sandbox` content policy.
- Attachment downloads are always `application/octet-stream` with
  `Content-Disposition: attachment`.

[Unreleased]: https://github.com/ebbbang/laravel-mailroom/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/ebbbang/laravel-mailroom/compare/v0.3.2...v0.4.0
[0.3.2]: https://github.com/ebbbang/laravel-mailroom/compare/v0.3.1...v0.3.2
[0.3.1]: https://github.com/ebbbang/laravel-mailroom/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/ebbbang/laravel-mailroom/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/ebbbang/laravel-mailroom/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/ebbbang/laravel-mailroom/releases/tag/v0.1.0
