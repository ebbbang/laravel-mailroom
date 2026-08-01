<?php

use Ebbbang\Mailroom\Http\Middleware\Authorize;

return [

    /*
    |--------------------------------------------------------------------------
    | Mailroom Master Switch
    |--------------------------------------------------------------------------
    |
    | When disabled, the "mailroom" mail transport refuses to be constructed
    | and the /mailroom routes are never registered. This defaults to off in
    | production so captured mail can never silently replace real delivery.
    |
    | If you genuinely want this in production, set MAILROOM_ENABLED=true.
    |
    */

    'enabled' => env('MAILROOM_ENABLED', env('APP_ENV') !== 'production'),

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

    'path' => env('MAILROOM_PATH', 'mailroom'),

    'domain' => env('MAILROOM_DOMAIN'),

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
        'disk' => env('MAILROOM_DISK', 'local'),
        'path' => env('MAILROOM_STORAGE_PATH', 'mailroom'),
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
        'connection' => env('MAILROOM_DB_CONNECTION'),
        'messages_table' => 'mailroom_messages',
        'attachments_table' => 'mailroom_attachments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Forwarding
    |--------------------------------------------------------------------------
    |
    | By default the mailroom transport is terminal -- mail is captured and
    | goes no further. Name another configured mailer here to also deliver the
    | message after capturing it, which is useful on staging.
    |
    */

    'forward' => env('MAILROOM_FORWARD'),

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | "retention_days" is the default age cutoff for `mailroom:prune`. Set
    | "schedule" to a cron expression (or a frequency method name such as
    | "daily") to have the package register the prune command on the scheduler
    | for you. Null leaves scheduling entirely up to you.
    |
    */

    'prune' => [
        'retention_days' => env('MAILROOM_RETENTION_DAYS', 7),
        'schedule' => env('MAILROOM_PRUNE_SCHEDULE'),
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

    /*
    |--------------------------------------------------------------------------
    | Attachment Previews
    |--------------------------------------------------------------------------
    |
    | Images, SVG, PDF, audio, video, text, CSV, JSON, source files, calendar
    | invites and nested .eml messages are previewed in the mailbox. Office
    | documents and archives are download-only -- see the README for why.
    |
    | "max_inline_bytes" caps the text-shaped kinds, which are read and escaped
    | server-side rather than streamed. Images and media are not affected by it
    | because the browser streams those itself.
    |
    | Turning this off unregisters the preview route entirely and leaves every
    | attachment as a plain download.
    |
    */

    'preview' => [
        'enabled' => env('MAILROOM_PREVIEW', true),

        // Text-shaped previews are rendered into the page itself, so this also
        // bounds how large the mailbox HTML can get. 512 KB is far more text
        // than anyone reads in a preview; larger files offer a download link.
        'max_inline_bytes' => 512 * 1024,

        'max_csv_rows' => 200,
    ],

];
