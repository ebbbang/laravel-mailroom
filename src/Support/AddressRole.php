<?php

namespace Ebbbang\Mailroom\Support;

/**
 * The part an address plays on a captured message.
 *
 * The first five are headers, as the recipient's mail client would show
 * them. The envelope pair is what the transport was actually asked to
 * deliver: it includes Bcc, and a caller can point it somewhere else
 * entirely, which is why it is kept apart from the headers rather than
 * merged into them.
 */
enum AddressRole: string
{
    case From = 'from';
    case To = 'to';
    case Cc = 'cc';
    case Bcc = 'bcc';
    case ReplyTo = 'reply_to';
    case EnvelopeFrom = 'envelope_from';
    case EnvelopeTo = 'envelope_to';

    /**
     * Whether an address in this role is someone the message was from or to,
     * which is what the People sidebar counts and filters by.
     *
     * Reply-To is a routing instruction rather than a party to the message,
     * and the envelope repeats addresses the headers already name, so
     * counting either would inflate every person's totals.
     */
    public function isPerson(): bool
    {
        return match ($this) {
            self::From, self::To, self::Cc, self::Bcc => true,
            default => false,
        };
    }

    /**
     * SMTP's MAIL FROM and RCPT TO carry bare addresses. Symfony builds the
     * envelope from the header Address objects, names and all, so a name on
     * an envelope row would be one the delivery never had.
     */
    public function isEnvelope(): bool
    {
        return $this === self::EnvelopeFrom || $this === self::EnvelopeTo;
    }

    public function isHeaderRecipient(): bool
    {
        return match ($this) {
            self::To, self::Cc, self::Bcc => true,
            default => false,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function people(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role->isPerson()));
    }

    /**
     * @return array<int, self>
     */
    public static function headerRecipients(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role->isHeaderRecipient()));
    }
}
