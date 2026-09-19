<?php

namespace Ebbbang\Mailroom\Exceptions;

use RuntimeException;

class CannotCheckSpamException extends RuntimeException
{
    public static function noDriverConfigured(): self
    {
        return new self(
            'No spam check driver is configured. Set MAILROOM_SPAM_DRIVER=postmark to check messages '
            .'against SpamAssassin through Postmark.'
        );
    }

    public static function unknownDriver(string $driver): self
    {
        return new self(sprintf(
            'Cannot check spam with driver [%s]. The driver that exists is "postmark".',
            $driver
        ));
    }

    public static function nothingToCheck(): self
    {
        return new self(
            'The stored copy of this message is missing, so there is nothing to check. Its raw MIME was '
            .'either never written or has since been removed from the disk.'
        );
    }

    public static function unreachable(string $endpoint, string $reason): self
    {
        return new self(sprintf('Could not reach %s: %s', $endpoint, $reason));
    }

    /**
     * The service answered, and said no. Its own words are worth more than
     * anything we could write here, so they are passed straight through.
     */
    public static function refused(string $reason): self
    {
        return new self(sprintf('The spam check was refused: %s', $reason));
    }
}
