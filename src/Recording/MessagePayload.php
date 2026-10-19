<?php

namespace Ebbbang\Mailroom\Recording;

use DateTimeInterface;

/**
 * Everything worth knowing about one outgoing message, extracted from the
 * Symfony objects and normalized into plain values ready for persistence.
 */
final readonly class MessagePayload
{
    /**
     * @param  array<int, AddressPayload>  $addresses
     * @param  array<string, string>  $headers
     * @param  array<int, string>  $tags
     * @param  array<string, string>  $metadata
     * @param  array<int, AttachmentPayload>  $parts
     */
    public function __construct(
        public string $mailer,
        public ?string $subject,
        public ?string $messageId,
        public array $addresses,
        public ?string $htmlBody,
        public ?string $textBody,
        public array $headers,
        public array $tags,
        public array $metadata,
        public ?DateTimeInterface $sentAt,
        public array $parts,
    ) {}
}
