# Contributing

Thanks for taking the time. Bug reports, documentation fixes and pull requests are all welcome.

## Getting set up

```bash
git clone https://github.com/ebbbang/laravel-mailroom.git
cd laravel-mailroom
composer install
```

That is the whole setup. The package is developed against [Orchestra Testbench](https://packages.tools/testbench), so there is no separate Laravel application to create. `composer install` scaffolds one under `vendor/orchestra/testbench-core/laravel`.

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

### Coverage

With a coverage driver installed:

```bash
composer test:coverage
```

CI runs the same thing on every push and writes the report into that run's summary. Nothing fails on a threshold.

### Blade formatting needs Node

Pint formats Blade through prettier, so the first `composer lint` will ask to install a few npm packages. That is a contributor-only requirement. The package itself still ships no assets and needs no build step.

Prettier reflows whitespace, which is right for HTML and wrong for templates rendered as plain text or markdown, where a blank line between paragraphs is content rather than layout. Mark those with `{{-- prettier-ignore --}}` on the first line; the comment disappears at render time. No test will catch it if you forget, so treat any reformatting of a mail template in your diff as a bug.

### The documentation site

The docs are the Markdown files in `docs/`, which read on GitHub as they are and build into the site at [ebbbang.github.io/laravel-mailroom](https://ebbbang.github.io/laravel-mailroom/) with VitePress. After `npm install`:

```bash
npm run docs:dev       # live-reloading site at http://localhost:5173/laravel-mailroom/
npm run docs:build     # the production build; fails on a dead link
npm run docs:preview   # serves that build as Pages will
```

Keep the pages to Markdown that GitHub renders too: relative links between pages (`./forwarding.md#keeping-it-safe`), GitHub alerts (`> [!NOTE]`) for notes, and no front matter or VitePress containers. Open each page with a sentence or two, at most 160 characters, saying what it covers: that paragraph is what a shared link or a search result shows. New pages go in the sidebar in `docs/.vitepress/config.mts` and in the list in `docs/README.md`. The build fails, naming the page, if one is missing from the sidebar or opens any other way. Pushing to `main` deploys the site.

### Screenshots

Screenshots live in `docs/images/`, the README's included. Take them from the demo mailbox with the browser 1440 wide at a device scale factor of 2, leaving a margin of the page around anything with rounded corners. In a docs page, link them as `<img src="./images/name.png" width="…" alt="…">`, with the width set to half the file's so they show at their real size. Then compress them:

```bash
npm run images
```

That gives every PNG a 256-colour palette, which cuts a screenshot of the mailbox to about a third of its size with no visible difference. Images it has already compressed are left alone, so it is safe to run over the whole set.

The card a shared link shows, and the touch icon, are rendered from `scripts/share-images.html` into `docs/public/`; the comment at the top of that file says how. Rendering them again after retaking the screenshot keeps the card current.

### Why `composer test` runs two passes

Testbench gives every worker the same skeleton application, so a test that writes into it is visible to all the others. `InstallCommandTest` does exactly that: publishing puts a `config/mailroom.php` in the directory each worker boots from, and testbench globs that directory and then requires what it found, so removing the file in teardown makes a concurrent boot fail on a path that existed a moment earlier. Its `.env` fixtures can likewise be read mid-rewrite.

So it carries `#[Group('publishes-files')]`, and `composer test` runs everything else in parallel first, then that group on its own:

```bash
composer test:parallel   # everything except the group
composer test:isolated   # just the group, single process
```

If you add a test that writes into the skeleton, whether by publishing, by touching `.env`, or by writing anything under `config_path()`, put it in that group. The symptom otherwise is an unrelated test failing intermittently on a different worker, which is a miserable thing to debug.

## The demo mailbox

`composer serve` creates the database, migrates it, seeds it and starts the server, so a clean checkout gets you a populated mailbox in one step. Re-running it just starts the server, because each step is a no-op once done.

The seeder aims at **one message per branch of the UI**, so every state can be inspected without composing anything by hand. Scenario subjects are prefixed with what they demonstrate:

```
[all kinds]        every previewable attachment type in one message
[html only]        no text part, so no Text tab
[text only]        no HTML part
[no body]          opens on Headers instead
[markdown]         a markdown mailable
[responsive]       a breakpoint, so the preview width switch has something to do
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
[secondary mailer] a second mailer, so the sidebar's Mailers section appears
[queued]           dispatched through the queue
```

Plus ~55 ordinary messages spread over 45 days, which gives three pages of pagination, something for search to match, and a range of ages so `mailroom:prune --days=7` and `--days=30` both have work to do.

```bash
php artisan demo:seed              # skips if the mailbox already has mail
php artisan demo:seed --fresh      # wipe and reseed
php artisan demo:seed --filler=0   # scenarios only, no padding
```

### Signing in

Read state belongs to a person, so the demo app carries throwaway sign-in routes:

```
/login/rachel    # or sam, dara, kit, yuki: any of the seeded people
/logout
```

The seeder marks a handful of messages read for Rachel, so switching between two people shows two different mailboxes rather than two identical unread lists. Signed out, the mailbox has no read state at all. The package ships no login of its own and defers to the host application's, which here is the demo app.

### Trying the spam check

The demo ships with no spam driver, which is what a fresh install looks like, so the Spam Check pane explains how to switch it on rather than offering a button. To see it working:

```bash
export MAILROOM_SPAM_DRIVER=postmark
composer serve
```

Clicking **Check with Postmark** then really does post that message to Postmark, so it is a genuine outbound request. The seeded mail is fabricated, so there is nothing sensitive in it.

The seeder also stamps three made-up results onto scenario messages, one in each colour band (under 2, 2 to 5, and over SpamAssassin's threshold of 5), so every state of the pane and the Spam checked mailbox can be reviewed without sending anything at all.

Every attachment is generated at runtime, so no binaries are committed. The one exception is a 1.6 KB MP4 held as base64. A valid video file cannot be assembled in code the way the PDF and WAV fixtures are, and shelling out to ffmpeg would mean the video scenario vanished on machines without it.

If you add a UI state, add a scenario for it. The seeder is the only way a reviewer can see your change without composing mail by hand.

## What CI checks

Eleven combinations, so a change that only works on your PHP version will be caught:

| | |
|---|---|
| PHP | 8.2, 8.3, 8.4, 8.5 |
| Laravel | 11, 12, 13 |
| Excluded | PHP 8.5 × Laravel 11, PHP 8.2 × Laravel 13, neither being a supported pairing |
| Plus | one `--prefer-lowest` run on PHP 8.2 × Laravel 12 |

Testbench majors track Laravel majors and cap the usable PHPUnit version, so the workflow pins both together. Constraining the framework alone lets testbench drag in a newer Laravel than the cell intends.

Two jobs run beside the matrix: `rector & pint`, which fails on any diff either tool would make, and `coverage`, which reports line coverage into the run's summary without failing on it.

## Ideas worth building

None of these is scheduled, and none is being worked on. They are here because each has been thought through, or partly built, far enough that the notes save whoever picks one up from finding the same things out again. A pull request for any of them is welcome, and so is the argument that one of them is a bad fit, best made in the [Ideas discussions](https://github.com/ebbbang/laravel-mailroom/discussions/categories/ideas).

### Forward history

A message does not record that it has already been forwarded, so two testers can send the same mail to the same inbox without either knowing. A `mailroom_forwards` table would fix it: message id, destination, who sent it, which mailer, and when. A `MailroomForward` model and a `forwards()` relation on `MailroomMessage`, surfaced on the message itself so it is visible where the decision to forward is made.

Two things to know going in. Migrations load from the package and are deliberately not publishable, so an existing install picks a new table up with a plain `migrate` and there is no publish step to document. And a new child table follows the deletion invariant: delete it explicitly in `MailroomMessage::booted()` as well as leaving it to the foreign key cascade, the way attachments, reads and addresses already do.

### A local spamd driver

The spam check posts to Postmark, which means the message leaves your network. A team that would rather it did not could run SpamAssassin themselves and get the same scores from a local daemon. `SpamChecker` already branches on `mailroom.spam.driver`, and the result shape and the pane that renders it already exist, so this is a driver and little else. Mailpit talks to SpamAssassin 3 and 4 over its own protocol and is worth reading for the wire format.

It needs a daemon running, which most Laravel developers do not have. That is the only reason Postmark came first.

### A link checker

Verify that the links in a message actually resolve. Of everything on this list it is the one that is *fully* verifiable: a broken link is a fact, where a spam score is an estimate. The thing worth catching is a staging URL that escaped into production mail, which no amount of template review reliably finds.

It would make the mailbox fetch URLs taken from captured content, and captured content is attacker-controlled. So it wants the spam check's shape exactly: off unless configured, nothing sent without a deliberate click, and a bound on how much it will fetch. Worth settling up front whether it follows redirects, and what it does with `mailto:` links and the tracking URLs that expect to be clicked once.

### What produced the message

The mailable class, the Blade view, and the variables passed to it, shown beside the rendered result. It answers the question the rendered message cannot: not what went out, but which branch produced it.

This one is a real architectural change rather than a feature bolted on. The transport sees only the finished Symfony message, deliberately, and that is what makes capture complete and framework-version-proof: it works for anything that reaches a mailer, however Laravel built it. Reaching the mailable and its view data means hooking `MessageSending` and `MessageSent` as well, and correlating the two with whatever the transport recorded. Worth prototyping before anyone commits to it.

## Pull requests

- **One concern per pull request.** A refactor bundled with a fix is hard to review and harder to revert.
- **Add a test.** Anything touching capture, the mailbox or previews should fail without your change. Please check that it does, rather than only that it passes with it.
- **Update the docs** when behaviour changes: the page in `docs/` that describes it, `config/mailroom.php` comments, and the tables in `docs/configuration.md`.
- **Add a `CHANGELOG.md` entry** under `## [Unreleased]` when people using the
  package will notice the change. Keep sections in Keep a Changelog's order
  (Added, Changed, Deprecated, Removed, Fixed, Security) and use only those
  headings. Tagging a release publishes that section verbatim as the GitHub
  release notes, read by someone deciding whether to upgrade, so the docs site,
  CI, tooling, the demo workbench and repository settings stay out of it.
- Match the surrounding style. Pint settles formatting; the comment density and naming are worth matching by eye.

## Security

Please do not open a public issue for a vulnerability. See [SECURITY.md](SECURITY.md) for how to report privately, and for the threat model. The mailbox deliberately renders attacker-controlled content, and it is worth reading how that is contained before reporting.
