# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`ebbbang/laravel-mailroom` — a standalone Laravel package (namespace `Ebbbang\Mailroom\`, PSR-4 from `src/`). It registers a `mailroom` mail transport that captures outgoing mail to the database plus a disk, and serves a Blade mailbox UI at `/mailroom`.

There is no host application. Development runs against [Orchestra Testbench](https://packages.tools/testbench): `composer install` scaffolds a skeleton app under `vendor/orchestra/testbench-core/laravel`, and `workbench/` holds the demo app used by `composer serve`.

## Commands

```bash
composer test          # test:parallel then test:isolated -- the full suite
composer test:parallel # everything except the publishes-files group, via paratest
composer test:isolated # just that group, single process
composer test:serial   # the whole suite through phpunit, when parallel output is hard to read

composer lint          # rector then pint -- rewrites files
composer lint:check    # both in dry-run; this is what CI runs

composer serve         # build, create sqlite db, migrate, seed, serve the demo at :8000/mailroom
composer seed          # wipe and reseed the demo mailbox
```

Running one test or one file:

```bash
vendor/bin/phpunit --filter=it_captures_a_raw_message
vendor/bin/phpunit tests/Feature/ForwardMessageTest.php
```

Use `phpunit` rather than `paratest` for a single test — paratest exists only to parallelise the full run.

### Why `composer test` is two passes

Testbench gives every parallel worker the *same* skeleton application, so a test that writes into it corrupts the others. Tests that publish config, rewrite `.env`, or touch anything under `config_path()` carry `#[Group('publishes-files')]` and run alone afterwards. Add that attribute to any new test that writes into the skeleton — otherwise the symptom is an unrelated test failing intermittently on a different worker.

## Architecture

### The capture path

```
MAIL_MAILER=mailroom
  → MailroomServiceProvider::registerTransport()   callAfterResolving('mail.manager')
  → TransportFactory::make()                       throws if mailroom.enabled is false
  → MailroomTransport::send()                      terminal: nothing is relayed onward
  → MessageRecorder::record()                      Symfony message → row + blobs
  → MailroomMessage row  +  RawMessageStore files
  → MessageStored event
```

`MailroomTransport` implements `TransportInterface` directly rather than extending Symfony's `AbstractTransport`, since there is no endpoint to talk to — it is modelled on `Illuminate\Mail\Transport\ArrayTransport`. Capturing at the transport layer (not via a `MessageSent` listener) is what gives access to the finished MIME, attachments and all.

Two subtleties in `MessageRecorder` that are easy to break:

- `getPreparedHeaders()` returns a clone and mints a *fresh* Message-ID and Date when absent. It is called once and the result reused for both the stored `.eml` and the database row; calling it twice makes the row describe a message that does not exist.
- Stream-backed bodies are resolved to strings before MIME generation, since generating would consume the stream and leave nothing for the row.

### Storage is split by design

Metadata and bodies live in the database so the list stays fast; raw MIME and attachment bytes go to a disk so the table stays small. `RawMessageStore` is the single seam to the filesystem, with everything for one message under `{path}/{uuid}/` — so deleting a message is one `deleteDirectory()` and can never orphan a part.

### The deletion invariant

**Every delete must go through model events**, because that is what removes the blobs alongside the row. Consequences already baked in, and worth preserving:

- `MailroomMessage` uses `Prunable`, deliberately not `MassPrunable`.
- `MessageController::clear()` chunks and calls `delete()` per model, then sweeps the directory.
- `migrate:fresh` fires no model events, so `FlushStorageOnDatabaseRefresh` listens for `DatabaseRefreshed` — and only flushes when the refreshed connection matches `mailroom.database.connection`.
- That listener is **not registered during tests**: `RefreshDatabase` runs `migrate:fresh` before a test can call `Storage::fake()`, so it would delete the developer's real captured mail.

### The enabled gate

`Mailroom::enabled()` (config `mailroom.enabled`, off in production by default) gates four separate things, and each is a deliberate choice:

| Gated | Why |
|---|---|
| Route registration | `/mailroom` 404s rather than 403s — no route, no surface |
| `loadMigrationsFrom` | a production deploy gains no tables (this is also why migrations are **not publishable**) |
| `TransportFactory::make()` | throws at construction, so a misconfigured mailer fails loudly instead of swallowing mail |
| Schedule registration | never adds work to an application that did not ask |

### Access control

`Mailroom::check()` has three escalating levers in precedence order: `Mailroom::auth()` callback → `Gate::has('viewMailroom')` → `local` environment only. The `Authorize` middleware just calls it. Forwarding is a *separate* privilege (`canForwardFrom()`), requiring an authenticated user outside `local`.

### The mailbox UI

Server-rendered Blade in `resources/views/`, with styles in `partials/styles.blade.php` and small vanilla scripts inlined via `@push('scripts')`. **The package ships no assets and needs no build step** — keep it that way; consumers get working styles with zero setup and no stale-asset failure mode.

Formatting them is a different matter: `pint.json` enables `Pint/laravel_blade`, which shells out to prettier, so `composer lint` needs `npm install` locally and the CI lint job installs it. Pint errors rather than installing on demand.

**Prettier reflows whitespace.** That is correct for HTML and wrong for anything rendered as `text/plain` or markdown, where a blank line between paragraphs *is* the content — it will silently join short paragraphs onto one line, depending on print width. Mark those templates with `{{-- prettier-ignore --}}`, as `tests/Fixtures/views/order-shipped-text.blade.php` does; the comment is stripped at render time and leaves the body byte-identical. Nothing catches this automatically: a collapsed paragraph in a text email fails no test.

Message bodies are attacker-controlled, so they are served from their own route into an `<iframe sandbox>` with neither `allow-scripts` nor `allow-same-origin`.

### Attachment previews

`AttachmentPreview` / `PreviewKind` decide how an attachment may be shown. The load-bearing rule: **the served content type comes from the allowlist, never from the attachment's `mime_type` column**, which is untrusted input. Text-shaped content is never served with a content type at all — it is escaped into Blade server-side. `AttachmentPreviewController` is deliberately separate from `AttachmentController`, which must keep forcing `application/octet-stream`. PDF is a documented exception to the sandbox CSP (Chrome's viewer needs it); see the docblock and `SECURITY.md` before changing any of this.

### Forwarding

`MessageForwarder` replays the *stored bytes*. Unchanged destination → byte-identical send; changed → `RawHeaderRewriter` retargets `To`/`Cc` and preserves the originals as `X-Mailroom-Original-*`. The route is not registered at all unless `forward.mailer` is set, and a mailroom transport is refused as the destination.

### Octane

The transport is built from the container that resolved `mail.manager`, not `$this->app`. Under Octane those differ — providers boot against the base app while requests run in a sandbox clone — and following the manager into the sandbox is what keeps `MessageStored` firing on the same dispatcher as Laravel's `MessageSent`. `Mailroom::$authUsing` is the only state that outlives a request; `flushState()` clears it.

## Conventions

- **Target the floor, not the ceiling.** `rector.php` pins `php82: true` and `UP_TO_LARAVEL_110` because the matrix still covers PHP 8.2 and Laravel 11. Do not reach for newer APIs.
- **No `declare(strict_types=1)`** — `SafeDeclareStrictTypesRector` is skipped deliberately, to match Laravel's own packages and to avoid changing coercion for `env()`-sourced config.
- Tests use PHPUnit attributes (`#[Test]`), extend `Ebbbang\Mailroom\Tests\TestCase`, and get `RefreshDatabase`, `Storage::fake('local')`, sqlite `:memory:` and `mail.default = mailroom` from it. Never rely on `testbench.yaml` in the suite — it configures the workbench demo only.
- Table names and the connection are config-driven via `getTable()` / `getConnectionName()` overrides, not properties.
- Every new setting gets an env variable, a `config/mailroom.php` entry with a comment, and a row in the README's configuration table.
- Comments here explain *why*, often at length, and load-bearing decisions are recorded where they can be found again. Match that density rather than stripping it.
- **Document what exists.** No sections describing what the software does not do, and no forward-looking version promises — a removal belongs in the changelog, and a plan that slips turns a promise into a lie.
- Commit messages are one-liners, imperative, with no trailing attribution.
- **Finish the work, report, and wait to be asked before committing.**

## Before opening a PR

- `composer lint` (CI fails on any diff either tool would make).
- **A new test must fail without its fix** — revert and check, don't just watch it pass.
- Add a `CHANGELOG.md` entry under `## [Unreleased]`, using only Keep a Changelog's headings in its order (Added, Changed, Deprecated, Removed, Fixed, Security). Tagging publishes that section verbatim as the GitHub release notes.
- If you add a UI state, add a scenario to `workbench/app/Console/Commands/SeedMailboxCommand.php` — the seeder aims at one message per branch of the UI, and it is the only way a reviewer sees your change without composing mail by hand.
- CI is eleven cells (PHP 8.2–8.5 × Laravel 11–13, plus one `--prefer-lowest`). The lowest-dependency run uses phpunit directly because paratest pins narrow PHPUnit ranges.

## Releasing

**Minor for new functionality, patch for fixes only.** The changelog entry tells you which: anything with an `### Added` section is a minor. Getting this backwards once meant renumbering a release before it shipped, because `^0.3` resolves to `0.3.*` — ship a fix as a minor and the people who need it never receive it.

- On a **minor**, bump the README's `Pin with ^0.x` note and the `SECURITY.md` supported-versions table. A patch leaves both alone.
- **Never rewrite a published changelog entry**, including its section order — the GitHub release notes were generated from that text. Correct forward in a later entry instead.
- Push, wait for the eleven cells, *then* tag. A tag push does not run `tests.yml`, which triggers only on branch pushes and pull requests.
- `.github/workflows/release.yml` turns the tag into a GitHub Release using that version's changelog section, and fails the job rather than publishing empty notes if the section is missing.
- **When `HEAD` is ahead of what you are tagging, name the commit** — `git tag v0.3.2 cfbbc74`. A bare tag would publish the wrong tree under that version.
