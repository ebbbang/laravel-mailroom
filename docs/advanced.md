# Events and platforms

## Events

```php
use Ebbbang\Mailroom\Events\MessageStored;

Event::listen(function (MessageStored $event) {
    $event->message;      // the stored MailroomMessage
    $event->sentMessage;  // Symfony's SentMessage
});
```

Laravel's own `MessageSent` carries no reference to the stored row, so this is how you get hold of it.

Addresses are rows in `mailroom_addresses`, one per address and role, read through the message:

```php
use Ebbbang\Mailroom\Support\AddressRole;

$message = $event->message;

$message->sender()?->address;                              // "orders@acme.test"
$message->addressesFor(AddressRole::To)->display();        // "Rachel Okonkwo <rachel@example.test>, sam@example.test"
$message->addressesFor(AddressRole::Cc, AddressRole::Bcc)->emails(); // every Cc, then every Bcc
$message->addressesFor(AddressRole::To)->includes('Rachel@Example.test'); // true, case-insensitive
$message->envelopeSender()?->address;                      // the SMTP envelope's MAIL FROM
```

The roles are `From`, `To`, `Cc`, `Bcc`, `ReplyTo`, and the SMTP envelope's `EnvelopeFrom` and `EnvelopeTo`, which include Bcc and can differ from the headers when a caller supplies an envelope of its own.

These read the `addresses` relation, so eager-load it when working through many messages:

```php
MailroomMessage::query()->with('addresses')->latest()->take(50)->get();
```

## Laravel Cloud and other ephemeral platforms

Mailroom works on Laravel Cloud, but **you must set `MAILROOM_DISK`**, because the default `local` disk is the wrong choice there. Laravel Cloud's docs are explicit that environment filesystems are

> ephemeral […] each replica of your compute cluster has its own filesystem. Thus, you should treat the filesystem as temporary, unshared disk space that is only consistent during a single request or job.

Message metadata and bodies live in the database, so those survive fine. The raw `.eml` and attachment bytes do not: they vanish on redeploy, and a message captured on one replica is unreadable from another. The mailbox stays usable, but `.eml` export, attachment downloads and embedded images break intermittently, which is worse than breaking outright.

Point the disk at persistent object storage, and set the master switch, since Cloud environments carry `APP_ENV=production` even when they are really staging:

```dotenv
MAIL_MAILER=mailroom
MAILROOM_DISK=<your object storage disk>
MAILROOM_ENABLED=true
```

If blobs do go missing the mailbox says so explicitly, rather than quietly hiding the download button, and it distinguishes a file that vanished from one deliberately skipped by `storage.max_attachment_size`.

The same applies to any ephemeral or multi-replica setup: containers without a shared volume, autoscaling groups, `/tmp`-backed disks.

**A queue worker is the most common way to meet this without thinking of yourself as ephemeral.** Mail dispatched to the queue is captured by the worker, so the worker writes the `.eml` and attachment bytes that the web process later reads back. Run those as two containers on one server with a `local` disk and neither can see the other's files: the message appears in the mailbox with its body intact and its attachments missing. Point `MAILROOM_DISK` at storage both processes share.

## Laravel Octane

Supported, and tested against a sandbox modelled on Octane's own `CurrentApplication::set()`.

The transport is built from whichever container resolved the mail manager, not from the one the service provider booted with. Under Octane those differ: providers boot against the base application while each request runs in a sandbox clone, and `mail.manager` is not one of Octane's warmed services, so it is rebuilt per request. Following it into the sandbox is what keeps `MessageStored` firing on the same dispatcher as Laravel's own `MessageSent`.

Nothing in the package holds per-request state between requests. The one piece of state that deliberately outlives a request is the `Mailroom::auth()` callback, set once from a service provider, which is the same lifetime a provider has under Octane. Two things follow:

- Do not capture a request, a user, or `$this` inside that closure. It receives the current request as its argument; use that.
- `Mailroom::flushState()` clears it, if a worker ever needs resetting.

Attachment bytes are read into memory to be written to the disk. That is a transient spike, but a spike nonetheless on long-lived workers. If your application mails large files, set `storage.max_attachment_size` so oversized parts are recorded without their payloads.

## Caveats

**BCC is not in the `.eml`.** Symfony strips the `Bcc` header when rendering a message, because it must never travel with it. The recipients are recorded in the database and shown in the mailbox, but the exported file will not contain them. That is correct MIME behaviour, not a bug.

**`Mail::fake()` bypasses this entirely.** It replaces the mail manager, so no transport runs. Use `Mail::assertSent()` for those tests; use Mailroom when you want to *look* at what was sent.
