<?php

namespace Ebbbang\TestMail\Exceptions;

use RuntimeException;

class TestMailDisabledException extends RuntimeException
{
    public static function forTransport(): self
    {
        return new self(
            'The [database] mail transport is disabled, so mail cannot be captured. '
            ."This package refuses to run when config('test-mail.enabled') is false, which is the default "
            .'whenever APP_ENV=production -- capturing production mail would silently replace real delivery. '
            .'Set TEST_MAIL_ENABLED=true to allow it here, or point MAIL_MAILER at a real transport.'
        );
    }
}
