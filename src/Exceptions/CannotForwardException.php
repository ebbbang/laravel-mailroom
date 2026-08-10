<?php

namespace Ebbbang\Mailroom\Exceptions;

use RuntimeException;

class CannotForwardException extends RuntimeException
{
    public static function noMailerConfigured(): self
    {
        return new self(
            'No mailer is configured to forward through. Set MAILROOM_FORWARD_MAILER to the name of a '
            .'mailer in config/mail.php that can actually deliver, such as "smtp".'
        );
    }

    public static function unknownMailer(string $mailer): self
    {
        return new self(sprintf(
            'Cannot forward through mailer [%s]: it is not defined in config/mail.php.',
            $mailer
        ));
    }

    /**
     * Forwarding through a mailroom mailer would capture the message a second
     * time rather than deliver it, leaving the tester still waiting for an
     * email that was never going to arrive.
     */
    public static function wouldCaptureAgain(string $mailer): self
    {
        return new self(sprintf(
            'Cannot forward through mailer [%s]: it is itself a mailroom mailer, so the message would be '
            .'captured again instead of delivered. Point MAILROOM_FORWARD_MAILER at a mailer that sends.',
            $mailer
        ));
    }

    public static function nothingToSend(): self
    {
        return new self(
            'The stored copy of this message is missing, so there is nothing to forward. Its raw MIME was '
            .'either never written or has since been removed from the disk.'
        );
    }

    public static function noSender(): self
    {
        return new self('This message records no sender, so there is no address to forward it from.');
    }
}
