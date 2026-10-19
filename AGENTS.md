# AGENTS.md

This file provides guidance to coding agents working with code in this repository.

## What this is

`ebbbang/laravel-mailroom` is a standalone Laravel package (namespace `Ebbbang\Mailroom\`, PSR-4 from `src/`). It registers a `mailroom` mail transport that captures outgoing mail to the database plus a disk, and serves a Blade mailbox UI at `/mailroom`.

There is no host application. Development runs against [Orchestra Testbench](https://packages.tools/testbench): `composer install` scaffolds a skeleton app under `vendor/orchestra/testbench-core/laravel`, and `workbench/` holds the demo app used by `composer serve`.

## Commands

```bash
composer test          # test:parallel then test:isolated -- the full suite
composer test:parallel # everything except the publishes-files group, via paratest
composer test:isolated # just that group, single process
composer test:serial   # the whole suite through phpunit, when parallel output is hard to read
composer test:coverage # the same, with a line coverage report; needs a coverage driver installed

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

Use `phpunit` rather than `paratest` for a single test. Paratest exists only to parallelise the full run.

### Why `composer test` is two passes

Testbench gives every parallel worker the *same* skeleton application, so a test that writes into it corrupts the others. Tests that publish config, rewrite `.env`, or touch anything under `config_path()` carry `#[Group('publishes-files')]` and run alone afterwards. Add that attribute to any new test that writes into the skeleton. Otherwise the symptom is an unrelated test failing intermittently on a different worker.

`composer serve` runs from that same skeleton, so **stop the demo server before running the suite.** A test run underneath it leaves the server on the in-memory `testing` connection, and every request then fails with "no such table: mailroom_messages", which looks like an application bug and is not. Afterwards, `composer clear` and `composer serve` rebuild the skeleton and the demo database.

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

`MailroomTransport` implements `TransportInterface` directly rather than extending Symfony's `AbstractTransport`, since there is no endpoint to talk to. It is modelled on `Illuminate\Mail\Transport\ArrayTransport`. Capturing at the transport layer (not via a `MessageSent` listener) is what gives access to the finished MIME, attachments and all.

Two subtleties in `MessageRecorder` that are easy to break:

- `getPreparedHeaders()` returns a clone and mints a *fresh* Message-ID and Date when absent. It is called once and the result reused for both the stored `.eml` and the database row; calling it twice makes the row describe a message that does not exist.
- Stream-backed bodies are resolved to strings before MIME generation, since generating would consume the stream and leave nothing for the row.

### Storage is split by design

Metadata and bodies live in the database so the list stays fast; raw MIME and attachment bytes go to a disk so the table stays small. `RawMessageStore` is the single seam to the filesystem, with everything for one message under `{path}/{uuid}/`, so deleting a message is one `deleteDirectory()` and can never orphan a part.

### Addresses

Every address on a message lives in `mailroom_addresses`: one row per address and `AddressRole` (From, To, Cc, Bcc, ReplyTo, EnvelopeFrom, EnvelopeTo), with its position in the header and the display name as it appeared on that message. Addresses are lowercased on the way in; names are not. That is deliberate, as in Gmail's reading view: the table is for matching, grouping and display, and the original casing stays visible where exact output is checked, in the stored headers (the Headers tab) and the raw `.eml` (the Raw tab). Envelope rows carry no name, because SMTP's MAIL FROM and RCPT TO have none.

Read them through `$message->addressesFor(AddressRole::To)`, `sender()` and `envelopeSender()`, and the `AddressCollection` they return (`display()`, `emails()`, `includes()`). Anything reading addresses across many messages eager-loads them with `with('addresses')`, as the list does. Never put addresses back into JSON or a column on the message: SQLite compiles `whereJsonContains` to a scalar comparison and matches nothing, and a text search over encoded JSON matches its key names ("address", "name") and misses any name stored as `\u` escapes.

The People sidebar and search use the four person roles only (`AddressRole::people()`): Reply-To is a routing instruction and the envelope repeats the headers, so counting either would inflate everyone's totals.

### The deletion invariant

**Every delete must go through model events**, because that is what removes the blobs alongside the row. Consequences already baked in, and worth preserving:

- `MailroomMessage` uses `Prunable`, deliberately not `MassPrunable`.
- Child rows (attachments, reads, addresses) are deleted explicitly in `MailroomMessage::booted()` as well as by the foreign key cascade, so a missing constraint or a driver that ignores one cannot orphan them. A new child table follows the same pattern.
- `MessageController::clear()` chunks and calls `delete()` per model, then sweeps the directory.
- `migrate:fresh` fires no model events, so `FlushStorageOnDatabaseRefresh` listens for `DatabaseRefreshed`, and only flushes when the refreshed connection matches `mailroom.database.connection`.
- That listener is **not registered during tests**: `RefreshDatabase` runs `migrate:fresh` before a test can call `Storage::fake()`, so it would delete the developer's real captured mail.

### The enabled gate

`Mailroom::enabled()` (config `mailroom.enabled`, off in production by default) gates four separate things, and each is a deliberate choice:

| Gated | Why |
|---|---|
| Route registration | `/mailroom` 404s rather than 403s: no route, no surface |
| `loadMigrationsFrom` | a production deploy gains no tables (this is also why migrations are **not publishable**) |
| `TransportFactory::make()` | throws at construction, so a misconfigured mailer fails loudly instead of swallowing mail |
| Schedule registration | never adds work to an application that did not ask |

### Access control

`Mailroom::check()` has three escalating levers in precedence order: `Mailroom::auth()` callback → `Gate::has('viewMailroom')` → `local` environment only. The `Authorize` middleware just calls it. Forwarding is a *separate* privilege (`canForwardFrom()`), requiring an authenticated user outside `local`.

### List state

`Mailroom::LIST_STATE` (`search`, `mailer`, `box`, `person`, `page`) is the one list of query parameters that every row link, every form acting on the list, and every redirect back to it must carry, or the reader loses their place. Read it with `Mailroom::listState($request)` and render it into forms with `partials/list-state-inputs`. A new filter is added to that constant, never to a call site.

Sidebar links are the exception, by design: the sidebar shows one view at a time (a mailbox, a person or a mailer), so each of its links is that view alone and drops the rest, search included. Exactly one sidebar item carries `aria-current="page"`, with a person outranking a mailer and a mailer outranking the mailbox.

### Search as you type

Both search boxes are ordinary GET forms that a script upgrades. The message search fetches the mailbox URL with an `X-Mailroom-Partial: list` header, and `MessageController::index()` answers that with the list partial alone, skipping the sidebar's queries, which are most of a page's cost on a large mailbox. Both responses carry `Vary: X-Mailroom-Partial` and the partial is `no-store`: they share a URL, and without that, Back after a search can render the bare fragment from the cache.

A search closes the open message, as Gmail does, swapping in the empty reader the page ships as `<template id="mr-reader-empty">`. That is deliberate: the message's forms and back link carry the list state from before the search, so keeping it open would send the reader back to a list no longer on screen. The people box fetches `GET /mailroom/people?q=`, which renders `partials/people`, the same fragment the page includes. Either script falls back to submitting its form if a fetch fails.

Matches are marked with `Highlight::mark()`, which escapes the text before adding `<mark>`. Captured mail is attacker-controlled, so highlighting never builds markup any other way.

The sidebar lists at most ten people (`MessageController::PEOPLE_SHOWN`) and counts the rest; the search box is how anyone else is found. The ranking is one grouped query over the `(address, role, mailroom_message_id, name)` index, which covers it without reading the table.

### Read state

Per person, and only for a signed-in one. `Mailroom::readerFor()` is the single seam: it stringifies `getAuthIdentifier()` and returns null when nobody is signed in, which switches the feature off entirely (no marking, no markers, no Unread mailbox, and the read routes 403). Everything downstream branches on that one value rather than consulting the guard itself.

`mailroom_reads` holds a plain string `reader_id` with **no foreign key**: captured mail may live on its own connection where the application's users table is not visible, and the identifier is a UUID or ULID on anything using `HasUuids` / `HasUlids`.

Three behaviours that look like bugs and are not. Opening a message marks it read on a **GET**, which is what reading is in any mail client; the poll endpoint is separate, so background polling can never mark anything. **Mark unread redirects to the list, not the message**, because arriving back on the message would mark it read again. And **the open message stays in the Unread list** although it is read, until the reader moves on, so its row does not vanish and `j`/`k` keep their place; the heading's count leaves it out, to agree with the sidebar.

### The mailbox UI

Server-rendered Blade in `resources/views/`, with styles in `partials/styles.blade.php` and small vanilla scripts inlined via `@push('scripts')`. **The package ships no assets and needs no build step.** Keep it that way; consumers get working styles with zero setup and no stale-asset failure mode.

Counts are written one way: `.mr-badge`, the pill the message list uses, in the detail pane's tab row as well. A second style for numbers is how one fact (an attachment count) came to look like two different things. The colour variants carry meaning: `.mr-badge-info` for attachments, and `SpamResult::band()` picks `-ok`, `-warn` or `-bad` for a spam score at `SpamResult::WARNING` (2) and `THRESHOLD` (5). Use `band()` rather than comparing a score by hand.

Sidebar counts cover the whole mailbox; the heading above the list counts the filtered list. On phones (below 900px) the list and the message are separate screens, switched by `.mr-reading` on the shell, which the server sets when a message is selected, so no script is involved. The message toolbar's labels are always in the DOM as `.mr-tool-label` (visually hidden on wide screens, visible on touch), which is where each icon button's accessible name comes from.

Formatting them is a different matter: `pint.json` enables `Pint/laravel_blade`, which shells out to prettier, so `composer lint` needs `npm install` locally and the CI lint job installs it. Pint errors rather than installing on demand.

**Prettier reflows whitespace.** That is correct for HTML and wrong for anything rendered as `text/plain` or markdown, where a blank line between paragraphs *is* the content. It will silently join short paragraphs onto one line, depending on print width. Mark those templates with `{{-- prettier-ignore --}}`, as `tests/Fixtures/views/order-shipped-text.blade.php` does; the comment is stripped at render time and leaves the body byte-identical. Nothing catches this automatically: a collapsed paragraph in a text email fails no test.

Message bodies are attacker-controlled, so they are served from their own route into an `<iframe sandbox>` with neither `allow-scripts` nor `allow-same-origin`. That sandbox is also why the preview widths are CSS on the parent and nothing else: with no `allow-same-origin` there is no `contentDocument` to measure, and with no `allow-scripts` the frame cannot `postMessage` a height back. Anything wanting to size a frame to its content is asking to loosen the sandbox.

**Two traps this UI has fallen into twice each, so check for both when adding a control:**

- **An author `display` rule beats the user agent's `[hidden] { display: none }`.** Any selector that sets `display` on something toggled by the `hidden` attribute needs a matching `[hidden] { display: none }`, or `:not([hidden])` on the rule itself, which survives reordering. It caught the forward modal in 0.4.0 (permanently open) and the HTML pane in 0.6.0 (rendering underneath every other tab). Neither fails a test; both are obvious in a browser.
- **`@push('scripts')` blocks are not covered by the `@if` that renders their markup.** A script pushed unconditionally ships to every message, including ones without the element it drives. Guard the push itself.

### Attachment previews

`AttachmentPreview` / `PreviewKind` decide how an attachment may be shown. The load-bearing rule: **the served content type comes from the allowlist, never from the attachment's `mime_type` column**, which is untrusted input. Text-shaped content is never served with a content type at all. It is escaped into Blade server-side. `AttachmentPreviewController` is deliberately separate from `AttachmentController`, which must keep forcing `application/octet-stream`. PDF is a documented exception to the sandbox CSP (Chrome's viewer needs it); see the docblock and `SECURITY.md` before changing any of this.

### Forwarding

`MessageForwarder` replays the *stored bytes*. Unchanged destination → byte-identical send; changed → `RawHeaderRewriter` retargets `To`/`Cc` and preserves the originals as `X-Mailroom-Original-*`. The route is not registered at all unless `forward.mailer` is set, and a mailroom transport is refused as the destination.

### Spam check

`Spam\SpamChecker` posts the stored bytes to Postmark's SpamCheck API and reports what comes back. **Mailroom scores nothing itself**, and that is the decision to preserve: SpamAssassin's weights are perceptron-trained against a corpus we do not have, so a rule set of our own would produce a number that looked like theirs and meant far less. Every comparable tool (Mailpit, HELO, Mailtrap) passes SpamAssassin through the same way.

The accuracy ceiling comes from where capture sits, not from effort. We see the message before the relay signs and sends it, so SPF, DKIM and DMARC results, the `Received` chain, and anything attached to a sending IP or domain are absent, and no driver can recover them. Content and headers are what get scored, and the docs say so rather than implying a delivery prediction.

It is **the only place captured mail leaves the application**, so it is shaped like forwarding: no driver means no route; a signed-in user is required outside `local`; a named rate limiter bounds it; and nothing is sent without a click. The result is stored on the message rather than refetched, since a round trip costs several seconds.

### Octane

The transport is built from the container that resolved `mail.manager`, not `$this->app`. Under Octane those differ, because providers boot against the base app while requests run in a sandbox clone, and following the manager into the sandbox is what keeps `MessageStored` firing on the same dispatcher as Laravel's `MessageSent`. `Mailroom::$authUsing` is the only state that outlives a request; `flushState()` clears it.

## Documentation

`docs/` is the reference, and the README is a short overview that links into it. The same Markdown is read two ways: on GitHub as files, and built by VitePress (`docs/.vitepress/`) into the site that `.github/workflows/docs.yml` deploys to GitHub Pages on every push to `main` that touches `docs/`. So the pages keep to Markdown both understand:

- Links between pages are relative and keep the extension (`./forwarding.md#keeping-it-safe`). GitHub follows them as files; VitePress turns them into clean URLs. The build fails on a dead link, which is the check.
- Notes are GitHub alerts (`> [!NOTE]`, `> [!IMPORTANT]`), which VitePress renders as well. No front matter and no `::: tip` containers on doc pages: GitHub shows both as stray text. `docs/index.md` is the one exception, since it is the site's landing page (`.vitepress/theme/Home.vue`) and nobody reads it on GitHub.
- A new page goes in the sidebar in `.vitepress/config.mts` and in the list in `docs/README.md`, which is what GitHub shows for the folder (the site build excludes it).
- `npm run docs:build` before pushing a docs change; `npm run docs:dev` to look at it.

**The tagline and description are written one way everywhere.** Tagline: "Keep every email your app sends. Read it in a real mailbox." Description: "A Laravel mail driver that stores outgoing mail in your database, plus a mailbox to read it." They appear in the README's opening, `composer.json` (Packagist shows it), the GitHub repository's description, the landing page (`Home.vue`), the site's `description` in `.vitepress/config.mts`, and the share card. Change one, change them all.

**A page's opening paragraph is its description.** The paragraph under the heading is what a shared link and a search result show, read straight from the page, so open every page with a sentence or two saying what it covers: plain enough to read as text, at most 160 characters, and not a lead-in ending in a colon. `npm run docs:build` fails, naming the page, when an opening breaks those rules or a page is missing from the sidebar.

**Sharing a link shows the card in `docs/public/social-card.png`.** `.vitepress/config.mts` gives every page Open Graph and X tags pointing at it, plus the page's own title, address and description. The card and the touch icon are rendered from `scripts/share-images.html`: open it in a browser, screenshot `#card` (1200×630) and `#icon` (180×180) at a scale factor of 1 into `docs/public/`, and run `npm run images`. 1200×630 is already twice the size apps show it at, so it is not taken at 2×. They live in `docs/public/` because the tags need an address that never changes; apps cache a preview by that address, so a new card also needs `?v=2` (and so on) added to its URL in the config, or they keep showing the old one.

**Screenshots are taken at 2× and compressed before they are committed.** Take them from the demo mailbox (`composer serve`, 1440 wide, light theme unless the page is about dark) with a device scale factor of 2, or they look soft on any high-density display. Crop with a margin of the surrounding page around anything with rounded corners (a card, the forward dialog), so its own corners sit inside the docs' frame instead of a second radius drawn over them. In a docs page, link the image as `<img src="./images/…" width="…" alt="…">` with the width in CSS pixels, half the file's, since Markdown's own image syntax cannot set one and a 2× file would otherwise show at twice its size. Every image lives in `docs/images/`, the README's and the landing page's included (`mailbox-light.png` and `mailbox-dark.png`), so there is one folder to look in. Save them there, then run `npm run images`. It gives each PNG a 256-colour palette, about a third of the size with no visible difference, and skips any image that already has one, so it never degrades a file by quantising it twice. The README links its screenshot by its raw GitHub URL on `main`, so renaming that file breaks the README.

## Conventions

- **Target the floor, not the ceiling.** `rector.php` pins `php82: true` and `UP_TO_LARAVEL_110` because the matrix still covers PHP 8.2 and Laravel 11. Do not reach for newer APIs.
- **No `declare(strict_types=1)`.** `SafeDeclareStrictTypesRector` is skipped deliberately, to match Laravel's own packages and to avoid changing coercion for `env()`-sourced config.
- Tests use PHPUnit attributes (`#[Test]`), extend `Ebbbang\Mailroom\Tests\TestCase`, and get `RefreshDatabase`, `Storage::fake('local')`, sqlite `:memory:` and `mail.default = mailroom` from it. Never rely on `testbench.yaml` in the suite; it configures the workbench demo only.
- Table names and the connection are config-driven via `getTable()` / `getConnectionName()` overrides, not properties.
- Every new setting gets an env variable, a `config/mailroom.php` entry with a comment, and a row in both tables in `docs/configuration.md`.
- Comments here explain *why*, often at length, and load-bearing decisions are recorded where they can be found again. Match that density rather than stripping it.
- **Document what exists.** No sections describing what the software does not do, and no forward-looking version promises. A removal belongs in the changelog, and a plan that slips turns a promise into a lie.
- Commit messages are one-liners, imperative, with no trailing attribution.
- **Finish the work, report, and wait to be asked before committing.**

## Before opening a PR

- `composer lint` (CI fails on any diff either tool would make).
- **A new test must fail without its fix.** Revert and check, don't just watch it pass. **The Blade view cache can make that check lie:** a restored file keeps measuring as failing until `vendor/orchestra/testbench-core/laravel/storage/framework/views` is cleared, and the symptom looks exactly like a test that is still correctly failing.
- **Assert on the markup, not the page.** The stylesheet is inlined, so every page carries every class name in the package. `assertDontSee('mr-unread-dot')` fails with no dot on screen. Scope to the element (`<nav class="mr-list">`) or assert on something only the markup has, like `aria-label="Unread"`.
- Add a `CHANGELOG.md` entry under `## [Unreleased]`, using only Keep a Changelog's headings in its order (Added, Changed, Deprecated, Removed, Fixed, Security). Tagging publishes that section verbatim as the GitHub release notes.
- If you add a UI state, add a scenario to `workbench/app/Console/Commands/SeedMailboxCommand.php`. The seeder aims at one message per branch of the UI, and it is the only way a reviewer sees your change without composing mail by hand.
- CI is eleven cells (PHP 8.2–8.5 × Laravel 11–13, plus one `--prefer-lowest`). The lowest-dependency run uses phpunit directly because paratest pins narrow PHPUnit ranges.

## Releasing

**Minor for new functionality, patch for fixes only.** The changelog entry tells you which: anything with an `### Added` section is a minor. Getting this backwards once meant renumbering a release before it shipped, because `^0.3` resolves to `0.3.*`. Ship a fix as a minor and the people who need it never receive it.

- **`composer update laravel/pint` before running the suite.** No `composer.lock` is committed, so CI always resolves the newest Pint. A local copy that lags passes a lint that CI then fails, and force pushing is blocked by the ruleset.
- **`git archive HEAD | wc -c`** before tagging, to confirm no development file leaked into the dist past `.gitattributes`.
- On a **minor**, bump the `Pin with ^0.x` note in `docs/installation.md` and the `SECURITY.md` supported-versions table. A patch leaves both alone. The version in the docs site's nav is read from the newest changelog heading, so it needs nothing.
- **Never rewrite a published changelog entry**, including its section order, because the GitHub release notes were generated from that text. Correct forward in a later entry instead.
- Push, wait for CI (the eleven cells, plus the lint and coverage jobs), *then* tag. A tag push does not run `tests.yml`, which triggers only on branch pushes and pull requests.
- `.github/workflows/release.yml` turns the tag into a GitHub Release using that version's changelog section, and fails the job rather than publishing empty notes if the section is missing.
- **When `HEAD` is ahead of what you are tagging, name the commit:** `git tag v0.3.2 cfbbc74`. A bare tag would publish the wrong tree under that version.
