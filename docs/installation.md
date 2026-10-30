# Installation

Install Mailroom with Composer and its installer, or by hand in two steps. It needs PHP 8.2 or later and Laravel 12 or 13.

Require the package as a development dependency:

```bash
composer require --dev ebbbang/laravel-mailroom
```

Then run the installer:

```bash
php artisan mailroom:install
```

The installer publishes the config, offers to set `MAIL_MAILER=mailroom` in your `.env`, and offers to run the migrations. Open `/mailroom` and your next email will be there.

**The installer is a convenience, not a requirement.** The service provider is auto-discovered, the config is merged from the package, the migrations are loaded from it, and a `mailroom` mailer is registered for you. So after `composer require`, these two do the same. Create the tables straight from the package:

```bash
php artisan migrate
```

And send mail through Mailroom:

```bash
echo "MAIL_MAILER=mailroom" >> .env
```

You only need `config/mailroom.php` in your project if you intend to change something in it.

To run unattended in CI, a Dockerfile or a provisioning script, every prompt has a flag. To publish the config, set the mailer and migrate without asking:

```bash
php artisan mailroom:install --no-interaction --set-mailer --migrate
```

Or to publish nothing and touch nothing:

```bash
php artisan mailroom:install --no-config --no-migrate
```

> [!IMPORTANT]
> **Pin with `^0.11` while this is 0.x.** Composer treats `^0.11` as `0.11.*` only, so moving to a 0.12 release needs a deliberate bump. Breaking changes may land in minor versions until 1.0.

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.2+ |
| Laravel | 12, 13 |

The test matrix covers PHP 8.2–8.5 against Laravel 12 and 13. Laravel 13 needs PHP 8.3 or later.

**On Laravel 11**, require `ebbbang/laravel-mailroom:^0.10`, the last release that supports it.
