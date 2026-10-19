# Production

Mailroom stays off in production unless you opt in, and old mail is pruned on whatever schedule you choose.

Disabled in production by default. The `mailroom` transport refuses to be constructed and the routes are never registered, so `/mailroom` 404s rather than 403s.

If you select the mailer anyway, it **throws** rather than silently accepting the message. Quietly swallowing production mail while looking like successful delivery is the worst possible failure here.

To use it in production regardless, opt in explicitly:

```dotenv
MAILROOM_ENABLED=true
```

Be deliberate about that. You are storing the full contents of every outgoing email, password reset links included, in your database and on a disk.

## Pruning

Delete messages older than `prune.retention_days`, seven days unless you change it:

```bash
php artisan mailroom:prune
```

Choose the age for one run with `--days` or `--hours`:

```bash
php artisan mailroom:prune --days=30
```

```bash
php artisan mailroom:prune --hours=6
```

See what would go without deleting anything:

```bash
php artisan mailroom:prune --pretend
```

Delete everything, stored files included:

```bash
php artisan mailroom:clear
```

Deletes go through model events so the raw `.eml` and attachment blobs are removed with the row. A mass delete would orphan every file on disk. The model is `Prunable`, so this works too:

```bash
php artisan model:prune --model="Ebbbang\Mailroom\Models\MailroomMessage"
```

Schedule it yourself, or set `prune.schedule` to a cron expression or frequency name (`daily`, `hourly`) and the package registers it for you.

## Wiping the database

`migrate:fresh` and `migrate:refresh` empty Mailroom's storage along with its tables, so wiping your database takes the captured mail with it, bodies and attachments included.

`migrate:rollback` and `migrate:reset` leave the stored files behind. Run `php artisan mailroom:clear` to remove them.

If you keep captured mail on its own connection with `MAILROOM_DB_CONNECTION`, only refreshing *that* connection clears the storage.
