# Contributing

Thanks for taking the time. Bug reports, documentation fixes and pull requests are all welcome.

## Getting set up

```bash
git clone https://github.com/ebbbang/laravel-mailroom.git
cd laravel-mailroom
composer install
```

That is the whole setup. The package is developed against [Orchestra Testbench](https://packages.tools/testbench), so there is no separate Laravel application to create — `composer install` scaffolds one under `vendor/orchestra/testbench-core/laravel`.

## Running things

```bash
composer test         # the suite, in parallel via paratest
composer test:serial  # the same suite through phpunit, when parallel output is hard to read
composer lint         # rector, then pint -- rewrites files
composer lint:check   # the same two in dry-run mode, which is what CI runs
composer serve        # the demo mailbox at http://127.0.0.1:8000/mailroom
composer seed         # wipe and reseed the demo mailbox
```

Run `composer lint` before opening a pull request. CI runs `lint:check`, which fails on any diff either tool would have made.

Both tools cache into `build/`, so repeat runs are fast and the directory is git-ignored.

### Blade formatting needs Node

Pint formats Blade through prettier, so the first `composer lint` will ask to install a few npm packages. That is a contributor-only requirement — the package itself still ships no assets and needs no build step.

Prettier reflows whitespace, which is right for HTML and wrong for templates rendered as plain text or markdown, where a blank line between paragraphs is content rather than layout. Mark those with `{{-- prettier-ignore --}}` on the first line; the comment disappears at render time. No test will catch it if you forget, so treat any reformatting of a mail template in your diff as a bug.

### Why `composer test` runs two passes

Testbench gives every worker the same skeleton application, so a test that writes into it is visible to all the others. `InstallCommandTest` does exactly that: publishing puts a `config/mailroom.php` in the directory each worker boots from, and testbench globs that directory and then requires what it found — so removing the file in teardown makes a concurrent boot fail on a path that existed a moment earlier. Its `.env` fixtures can likewise be read mid-rewrite.

So it carries `#[Group('publishes-files')]`, and `composer test` runs everything else in parallel first, then that group on its own:

```bash
composer test:parallel   # everything except the group
composer test:isolated   # just the group, single process
```

If you add a test that writes into the skeleton — publishing, `.env`, anything under `config_path()` — put it in that group. The symptom otherwise is an unrelated test failing intermittently on a different worker, which is a miserable thing to debug.

## The demo mailbox

`composer serve` creates the database, migrates it, seeds it and starts the server, so a clean checkout gets you a populated mailbox in one step. Re-running it just starts the server, because each step is a no-op once done.

The seeder aims at **one message per branch of the UI**, so every state can be inspected without composing anything by hand. Scenario subjects are prefixed with what they demonstrate:

```
[all kinds]        every previewable attachment type in one message
[html only]        no text part, so no Text tab
[text only]        no HTML part
[no body]          opens on Headers instead
[markdown]         a markdown mailable
[addressing]       Cc, Bcc, Reply-To, tags, metadata, custom header
[long]             a subject and recipient list that need truncating
[unicode]          CJK, RTL, emoji, zero-width space
[long body]        sixty paragraphs, for scrolling
[inline only]      embedded images and nothing attached
[preview states]   too large, empty, malformed JSON, truncated CSV
[hostile names]    a path-traversal filename and unpreviewable types
[skipped]          an attachment over storage.max_attachment_size
[envelope]         delivered somewhere other than the To header
[missing files]    row intact, blobs deleted
[secondary mailer] a second mailer, so the filter appears
[queued]           dispatched through the queue
```

Plus ~55 ordinary messages spread over 45 days, which gives three pages of pagination, something for search to match, and a range of ages so `mailroom:prune --days=7` and `--days=30` both have work to do.

```bash
php artisan demo:seed              # skips if the mailbox already has mail
php artisan demo:seed --fresh      # wipe and reseed
php artisan demo:seed --filler=0   # scenarios only, no padding
```

Every attachment is generated at runtime, so no binaries are committed. The one exception is a 1.6 KB MP4 held as base64 — a valid video file cannot be assembled in code the way the PDF and WAV fixtures are, and shelling out to ffmpeg would mean the video scenario vanished on machines without it.

If you add a UI state, add a scenario for it. The seeder is the only way a reviewer can see your change without composing mail by hand.

## What CI checks

Eleven combinations, so a change that only works on your PHP version will be caught:

| | |
|---|---|
| PHP | 8.2, 8.3, 8.4, 8.5 |
| Laravel | 11, 12, 13 |
| Excluded | PHP 8.5 × Laravel 11, PHP 8.2 × Laravel 13 — neither is a supported pairing |
| Plus | one `--prefer-lowest` run on PHP 8.2 × Laravel 12 |

Testbench majors track Laravel majors and cap the usable PHPUnit version, so the workflow pins both together. Constraining the framework alone lets testbench drag in a newer Laravel than the cell intends.

## Pull requests

- **One concern per pull request.** A refactor bundled with a fix is hard to review and harder to revert.
- **Add a test.** Anything touching capture, the mailbox or previews should fail without your change — please check that it does, rather than only that it passes with it.
- **Update the docs** when behaviour changes, including `config/mailroom.php` comments and the README's configuration table.
- **Add a `CHANGELOG.md` entry** under `## [Unreleased]`. Keep sections in Keep
  a Changelog's order — Added, Changed, Deprecated, Removed, Fixed, Security —
  and use only those headings. Tagging a release publishes that section verbatim
  as the GitHub release notes, so it is read far more often than it is written.
- Match the surrounding style. Pint settles formatting; the comment density and naming are worth matching by eye.

## Security

Please do not open a public issue for a vulnerability. See [SECURITY.md](SECURITY.md) for how to report privately, and for the threat model — the mailbox deliberately renders attacker-controlled content, and it is worth reading how that is contained before reporting.
