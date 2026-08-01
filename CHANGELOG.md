# Changelog

All notable changes to `ebbbang/laravel-mailroom` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html),
with the caveat that **while the version is below 1.0, breaking changes may
land in minor releases** — see the pinning note in the README.

## [Unreleased]

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

[Unreleased]: https://github.com/ebbbang/laravel-mailroom/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/ebbbang/laravel-mailroom/releases/tag/v0.1.0
