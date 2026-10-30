# Staging and QA

Run Mailroom on a shared staging environment, so testers read every email in the browser without a third-party catcher.

Mailroom is as useful on a shared staging environment as it is on your laptop. Testers get a mailbox they can read in the browser: no third-party catcher to sign up for, no inbox credentials to share around, and the mail stays inside your own infrastructure. The exceptions are [forwarding](./forwarding.md) and the [spam check](./spam-check.md), which are both off until you configure them, and even then send a message only when somebody clicks.

Captured mail stays captured, so your staging fixtures can use whatever addresses make the test realistic.

```dotenv
MAIL_MAILER=mailroom
MAILROOM_ENABLED=true
MAILROOM_DISK=<your object storage disk>
```

Three things to get right:

**Enable it explicitly.** Most staging platforms, Laravel Cloud and Forge and Heroku-style hosts among them, set `APP_ENV=production` even when the environment is really staging, and Mailroom stays off in production unless you say otherwise. That default is deliberate: see [Production](./production.md).

**Put it behind your login.** The mailbox holds the full contents of every email your app sends, password reset links included. On anything reachable from the internet, add `auth` to the middleware stack and gate it:

```php
'middleware' => ['web', 'auth', Authorize::class],

Gate::define('viewMailroom', fn (?User $user) => $user?->isQaTeam());
```

**Point the disk at persistent storage.** Staging platforms usually run ephemeral, multi-replica filesystems, where the default `local` disk loses attachments between deploys and between replicas. [Laravel Cloud and other ephemeral platforms](./advanced.md#laravel-cloud-and-other-ephemeral-platforms) covers this.

Add `MAILROOM_FORWARD_MAILER` and testers can also send a message to their own inbox when they want to check it in a real client. See [Forwarding a message](./forwarding.md).

Set a short `prune.retention_days` while you are there, so a long-lived staging box does not accumulate months of real-looking personal data.
