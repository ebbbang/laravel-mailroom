<?php

namespace Ebbbang\TestMail\Recording;

use DateTimeInterface;

/**
 * Everything worth knowing about one outgoing message, extracted from the
 * Symfony objects and normalized into plain arrays ready for persistence.
 */
final readonly class MessagePayload
{
    /**
     * @param  array<int, array{address: string, name: string|null}>  $from
     * @param  array<int, array{address: string, name: string|null}>  $to
     * @param  array<int, array{address: string, name: string|null}>  $cc
     * @param  array<int, array{address: string, name: string|null}>  $bcc
     * @param  array<int, array{address: string, name: string|null}>  $replyTo
     * @param  array<int, array{address: string, name: string|null}>  $envelopeRecipients
     * @param  array<string, string>  $headers
     * @param  array<int, string>  $tags
     * @param  array<string, string>  $metadata
     * @param  array<int, AttachmentPayload>  $parts
     */
    public function __construct(
        public string $mailer,
        public ?string $subject,
        public ?string $messageId,
        public array $from,
        public array $to,
        public array $cc,
        public array $bcc,
        public array $replyTo,
        public array $envelopeRecipients,
        public ?string $envelopeSender,
        public ?string $htmlBody,
        public ?string $textBody,
        public array $headers,
        public array $tags,
        public array $metadata,
        public ?DateTimeInterface $sentAt,
        public array $parts,
    ) {}
}
