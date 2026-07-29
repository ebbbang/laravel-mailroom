<?php

use Ebbbang\TestMail\Http\Middleware\Authorize;

return [

    /*
    |--------------------------------------------------------------------------
    | Test Mail Master Switch
    |--------------------------------------------------------------------------
    |
    | When disabled, the "database" mail transport refuses to be constructed
    | and the /test-mail routes are never registered. This defaults to off in
    | production so captured mail can never silently replace real delivery.
    |
    | If you genuinely want this in production, set TEST_MAIL_ENABLED=true.
    |
    */

    'enabled' => env('TEST_MAIL_ENABLED', env('APP_ENV') !== 'production'),

    /*
    |--------------------------------------------------------------------------
    | Mailbox Route
    |--------------------------------------------------------------------------
    |
    | The path, domain and middleware for the mailbox UI. This package never
    | ships its own login page -- add "auth" (or "auth:admin", etc.) to the
    | middleware stack to lean on your application's existing login flow.
    |
    */

    'path' => env('TEST_MAIL_PATH', 'test-mail'),

    'domain' => env('TEST_MAIL_DOMAIN'),

    'middleware' => ['web', Authorize::class],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Message metadata lives in the database so the mailbox can list and search
    | quickly. The raw MIME (.eml) and attachment bytes are written to a disk
    | instead, which keeps the table small even with large attachments.
    |
    | Set "max_attachment_size" to skip persisting attachment bytes above a
    | given size (in bytes). Metadata is still recorded. Null means no limit.
    |
    */

    'storage' => [
        'disk' => env('TEST_MAIL_DISK', 'local'),
        'path' => env('TEST_MAIL_STORAGE_PATH', 'test-mail'),
        'max_attachment_size' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Null uses your default connection. Point this at a separate connection
    | (e.g. a local sqlite file) if you would rather keep captured mail out of
    | your primary database.
    |
    */

    'database' => [
        'connection' => env('TEST_MAIL_DB_CONNECTION'),
        'messages_table' => 'test_mail_messages',
        'attachments_table' => 'test_mail_attachments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Forwarding
    |--------------------------------------------------------------------------
    |
    | By default the database transport is terminal -- mail is captured and
    | goes no further. Name another configured mailer here to also deliver the
    | message after capturing it, which is useful on staging.
    |
    */

    'forward' => env('TEST_MAIL_FORWARD'),

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | "retention_days" is the default age cutoff for `test-mail:prune`. Set
    | "schedule" to a cron expression (or a frequency method name such as
    | "daily") to have the package register the prune command on the scheduler
    | for you. Null leaves scheduling entirely up to you.
    |
    */

    'prune' => [
        'retention_days' => env('TEST_MAIL_RETENTION_DAYS', 7),
        'schedule' => env('TEST_MAIL_PRUNE_SCHEDULE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mailbox UI
    |--------------------------------------------------------------------------
    |
    | "per_page" controls pagination in the message list. "poll_interval"
    | is how often (in seconds) the list checks for new mail; set it to null
    | to disable polling entirely.
    |
    */

    'ui' => [
        'per_page' => 25,
        'poll_interval' => 5,
    ],

];
