<?php

namespace Ebbbang\Mailroom\Exceptions;

use RuntimeException;

class MailroomDisabledException extends RuntimeException
{
    public static function forTransport(): self
    {
        return new self(
            'The [mailroom] mail transport is disabled, so mail cannot be captured. '
            ."This package refuses to run when config('mailroom.enabled') is false, which is the default "
            .'whenever APP_ENV=production -- capturing production mail would silently replace real delivery. '
            .'Set MAILROOM_ENABLED=true to allow it here, or point MAIL_MAILER at a real transport.'
        );
    }
}
