<?php

namespace Ebbbang\TestMail\Recording;

/**
 * One attachment or inline part extracted from a message, before it is
 * written to disk.
 */
final readonly class AttachmentPayload
{
    public function __construct(
        public ?string $filename,
        public ?string $mimeType,
        public string $disposition,
        public ?string $contentId,
        public string $contents,
    ) {}

    public function size(): int
    {
        return strlen($this->contents);
    }

    public function isInline(): bool
    {
        return $this->disposition === 'inline';
    }
}
