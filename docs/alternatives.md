# Alternatives

A few packages already log mail to the database, [`shvetsgroup/laravel-email-database-log`](https://packagist.org/packages/shvetsgroup/laravel-email-database-log) and [`stackkit/laravel-database-emails`](https://packagist.org/packages/stackkit/laravel-database-emails) among them, and [`spatie/laravel-database-mail-templates`](https://packagist.org/packages/spatie/laravel-database-mail-templates) stores templates rather than sent mail.

Mailroom is a **development tool** rather than a logging or queueing layer: it exists to be *read*. That means the mailbox UI, attachment previews and `.eml` export, and a package that refuses to run in production unless you explicitly opt in.

If you would rather run a separate service, [Mailpit](https://mailpit.axllent.org) and [Helo](https://usehelo.com) catch SMTP outside your application entirely. Mailroom trades that isolation for needing nothing else installed, and for capturing what Laravel actually built rather than what reached an SMTP socket.

Different again are template previewers such as [Statamic's Mailables Viewer](https://statamic.com/addons/statamic/mailables-viewer), which find your `Mailable` classes and render them on demand with sample constructor data. Those are good for iterating on a template you are actively writing. Mailroom answers a different question: it stores the message your application really produced, with the values it really used, the attachments it really generated, and whichever conditionals really ran. Nothing has to be re-rendered for you to read it.
