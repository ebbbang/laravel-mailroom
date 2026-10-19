# Laravel Mailroom

[![Latest version](https://img.shields.io/packagist/v/ebbbang/laravel-mailroom.svg?style=flat-square)](https://packagist.org/packages/ebbbang/laravel-mailroom)
[![Tests](https://img.shields.io/github/actions/workflow/status/ebbbang/laravel-mailroom/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ebbbang/laravel-mailroom/actions/workflows/tests.yml)
[![Downloads](https://img.shields.io/packagist/dt/ebbbang/laravel-mailroom.svg?style=flat-square)](https://packagist.org/packages/ebbbang/laravel-mailroom)
[![License](https://img.shields.io/packagist/l/ebbbang/laravel-mailroom.svg?style=flat-square)](LICENSE)

**Keep every email your app sends. Read it in a real mailbox.**

A Laravel mail driver that stores outgoing mail in your database, plus a mailbox to read it.

`MAIL_MAILER=log` flattens a message into a log line and throws away the attachments. `MAIL_MAILER=array` forgets everything at the end of the request. Mailroom keeps the whole thing: full MIME, attachments, embedded images, tags, metadata, custom headers. And it gives you somewhere to look at it.

**Documentation: [ebbbang.github.io/laravel-mailroom](https://ebbbang.github.io/laravel-mailroom/)**

![The Mailroom mailbox: a sidebar of mailboxes and people, the message list, and an open message](https://raw.githubusercontent.com/ebbbang/laravel-mailroom/main/docs/images/mailbox-light.png)

## Installation

```bash
composer require --dev ebbbang/laravel-mailroom
```

```bash
php artisan mailroom:install
```

Open `/mailroom` and your next email will be there. Requires PHP 8.2+ and Laravel 12 or 13 (11 best-effort).

## Documentation

- [Installation](https://ebbbang.github.io/laravel-mailroom/installation): the installer's flags, doing it by hand, and requirements
- [Reading mail](https://ebbbang.github.io/laravel-mailroom/mailbox): mailboxes and people, the keyboard, read state, exports and attachment previews
- [Access control](https://ebbbang.github.io/laravel-mailroom/access-control): who can open the mailbox outside `local`
- [Forwarding a message](https://ebbbang.github.io/laravel-mailroom/forwarding): send a copy to a real inbox
- [Spam check](https://ebbbang.github.io/laravel-mailroom/spam-check): a SpamAssassin score through Postmark
- [Staging and QA](https://ebbbang.github.io/laravel-mailroom/staging): a mailbox your testers share
- [Production](https://ebbbang.github.io/laravel-mailroom/production): keeping it off, pruning and wiping
- [Configuration](https://ebbbang.github.io/laravel-mailroom/configuration): every setting and environment variable
- [Events and platforms](https://ebbbang.github.io/laravel-mailroom/advanced): `MessageStored`, Laravel Cloud, Octane and caveats
- [Alternatives](https://ebbbang.github.io/laravel-mailroom/alternatives): other packages, and Mailpit and Helo

The same pages are in [`docs/`](docs/README.md), readable here on GitHub.

## Changelog

See [CHANGELOG.md](https://github.com/ebbbang/laravel-mailroom/blob/main/CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](https://github.com/ebbbang/laravel-mailroom/blob/main/CONTRIBUTING.md). It covers the workbench demo mailbox, which seeds one message per branch of the UI so you can see every state without composing mail by hand, and running the docs site locally.

Please note the [Code of Conduct](https://github.com/ebbbang/laravel-mailroom/blob/main/CODE_OF_CONDUCT.md).

## Security

Please do not open a public issue for a vulnerability. [SECURITY.md](https://github.com/ebbbang/laravel-mailroom/blob/main/SECURITY.md) explains how to report privately, and documents the threat model. The mailbox renders attacker-controlled content by design, and how that is contained is worth reading before reporting.

## Credits

- [Ebrahim Bangdiwala](https://github.com/ebbbang)
- [All contributors](https://github.com/ebbbang/laravel-mailroom/contributors)

## License

MIT. See [LICENSE](LICENSE).
