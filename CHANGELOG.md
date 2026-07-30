# Changelog

All notable changes to `ebbbang/laravel-test-mail` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html),
with the caveat that **while the version is below 1.0, breaking changes may
land in minor releases** — see the pinning note in the README.

## [Unreleased]

## [0.1.0] - 2026-07-30

First release.

### Added

- A `database` mail transport that captures every outgoing message: HTML and
  text bodies, attachments, inline images, tags, metadata and custom headers.
  It hooks in as a Symfony transport, so it sees the finished message rather
  than reconstructing one.
- An auth-gated mailbox at `/test-mail` with search, a per-mailer filter,
  pagination, and light, dark and system themes. No build step and no
  published assets — the styles are inlined.
- Attachment previews in a lightbox: images, SVG, PDF, audio, video, text,
  Markdown, CSV and TSV as tables, pretty-printed JSON, source files, `.ics`
  invites and nested `.eml` messages. Media supports HTTP Range, so seeking
  works. Office documents and archives are download-only.
- Export as `.eml`, or as a standalone `.html` file with embedded images
  rewritten to `data:` URIs.
- Three levels of access control: local-only by default, a `viewTestMail`
  gate, or a `TestMail::auth()` callback. The package ships no login page and
  defers to the host application's.
- `test-mail:install`, `test-mail:prune` and `test-mail:clear` commands. The
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

[Unreleased]: https://github.com/ebbbang/laravel-test-mail/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/ebbbang/laravel-test-mail/releases/tag/v0.1.0
