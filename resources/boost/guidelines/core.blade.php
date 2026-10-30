{{-- prettier-ignore --}}
## Laravel Mailroom

Mailroom (`ebbbang/laravel-mailroom`) is a mail driver that stores every outgoing email in the database, plus a mailbox at `/mailroom` to read it. Nothing is delivered: mail sent through the `mailroom` mailer stops there. Documentation: https://ebbbang.github.io/laravel-mailroom/llms.txt

### Capturing mail

- Send through it with `MAIL_MAILER=mailroom` in `.env`. The mailer is registered by the package, so `config/mail.php` needs no entry.
- To capture some mail while the default mailer delivers the rest, send it with `Mail::mailer('mailroom')`.
- To see what an email looks like, send it and open `/mailroom`. Do not set up Mailpit, MailHog, Mailtrap or an SMTP container for this application: Mailroom already captures its mail, with nothing else to run.
- `mailroom.enabled` (`MAILROOM_ENABLED`) is off in production by default. While it is off, the routes and migrations are not registered and the `mailroom` mailer throws when used. Never set it to `true` in production unless the user asks: it stores every email, password reset links included.
- `Mail::fake()` replaces the mail manager, so nothing reaches Mailroom. Keep `Mail::assertSent()` for tests; Mailroom is for looking at real output.

### Access to the mailbox

With nothing configured, the mailbox opens in the `local` environment only. Elsewhere, define a `viewMailroom` gate, with a nullable user so guests are evaluated too:

@verbatim
<code-snippet name="Allow admins to open the mailbox" lang="php">
Gate::define('viewMailroom', fn (?User $user) => $user?->isAdmin());
</code-snippet>
@endverbatim

Or, for a rule a gate cannot express, a callback in a service provider. It receives the current request; do not capture a request or user in the closure:

@verbatim
<code-snippet name="Allow one IP address" lang="php">
use Ebbbang\Mailroom\Mailroom;

Mailroom::auth(fn (Request $request) => $request->ip() === '10.0.0.1');
</code-snippet>
@endverbatim

### Reading captured mail in code

`Ebbbang\Mailroom\Events\MessageStored` fires after each message is stored. Addresses are read through the message, by role, never from a column:

@verbatim
<code-snippet name="Listen for stored messages" lang="php">
use Ebbbang\Mailroom\Events\MessageStored;
use Ebbbang\Mailroom\Support\AddressRole;

Event::listen(function (MessageStored $event) {
    $message = $event->message; // Ebbbang\Mailroom\Models\MailroomMessage

    $message->sender()?->address;
    $message->addressesFor(AddressRole::To)->emails();
    $message->addressesFor(AddressRole::Cc, AddressRole::Bcc)->includes('someone@example.com');
});
</code-snippet>
@endverbatim

When reading many messages, eager-load the addresses: `MailroomMessage::query()->with('addresses')->latest()->get()`.

### Deleting captured mail

Delete through the model or the commands, never with a query-builder delete. The raw `.eml` and attachment files are removed by model events, so `MailroomMessage::query()->delete()` leaves them orphaned on the disk.

- `php artisan mailroom:prune` deletes messages older than `MAILROOM_RETENTION_DAYS` (7). `--days`, `--hours` and `--pretend` are available.
- `php artisan mailroom:clear` deletes everything, files included.

### Configuration

Every setting has an environment variable, so `config/mailroom.php` only needs publishing to change something. The ones most often needed:

- `MAILROOM_DISK`: where raw messages and attachments are stored. On Laravel Cloud, with queue workers in separate containers, or anywhere the filesystem is not shared, set it to object storage.
- `MAILROOM_PATH`: the mailbox's URL path (`mailroom`).
- `MAILROOM_FORWARD_MAILER`: a real mailer to forward captured messages through. Forwarding is off until it is set, and it must not be a `mailroom` mailer.
- `MAILROOM_SPAM_DRIVER=postmark`: scores a message with SpamAssassin through Postmark's API, only when someone clicks Spam Check.
- `MAILROOM_RETENTION_DAYS` with `MAILROOM_PRUNE_SCHEDULE` (`daily`, or a cron expression): keeps captured mail no longer than needed.

Captured mail stays in the application's own database and storage. Forwarding and the spam check are the only ways it leaves, the spam check to a third party, so configure either only when the user asks for it.
