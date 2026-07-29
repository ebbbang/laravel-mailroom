<?php

namespace Ebbbang\TestMail\Recording;

use DateTimeInterface;
use Ebbbang\TestMail\Models\TestMailMessage;
use Ebbbang\TestMail\Storage\RawMessageStore;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\DateHeader;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Mime\RawMessage;

/**
 * Turns an outgoing Symfony message into a persisted TestMailMessage plus
 * its blobs on disk.
 */
class MessageRecorder
{
    public function __construct(protected RawMessageStore $store) {}

    public function record(RawMessage $message, Envelope $envelope, string $mailer): TestMailMessage
    {
        $uuid = (string) Str::uuid();

        // Resolve any stream-backed bodies to strings up front. Generating the
        // MIME would consume those streams, leaving nothing to read back for
        // the database row -- normalizing first keeps both artifacts identical.
        $this->normalizeBodies($message);

        /*
         * Prepare the headers exactly once and reuse that one instance for
         * both the stored .eml and the database row.
         *
         * getPreparedHeaders() returns a *clone* and mints a fresh Message-ID
         * and Date whenever the message lacks them, so calling it a second
         * time would hand back a different Message-ID than the one written
         * into the file -- the row would describe a message that does not
         * exist. Hoisting it is what keeps the two in agreement.
         */
        $preparedHeaders = $message instanceof Message ? $message->getPreparedHeaders() : null;

        [$rawPath, $size] = $this->store->putRaw($uuid, $this->rawChunks($message, $preparedHeaders));

        $payload = $this->payload($message, $envelope, $mailer, $preparedHeaders);

        $model = TestMailMessage::create([
            'uuid' => $uuid,
            'mailer' => $payload->mailer,
            'subject' => $payload->subject,
            'message_id' => $payload->messageId,
            'from' => $payload->from,
            'to' => $payload->to,
            'cc' => $payload->cc,
            'bcc' => $payload->bcc,
            'reply_to' => $payload->replyTo,
            'envelope_recipients' => $payload->envelopeRecipients,
            'envelope_sender' => $payload->envelopeSender,
            'html_body' => $payload->htmlBody,
            'text_body' => $payload->textBody,
            'headers' => $payload->headers,
            'tags' => $payload->tags,
            'metadata' => $payload->metadata,
            'raw_path' => $rawPath,
            'size' => $size,
            // Real files only. Inline parts are embedded images belonging to
            // the body, and counting them here would contradict the
            // attachments tab, which lists files alone.
            'attachment_count' => count(array_filter(
                $payload->parts,
                fn (AttachmentPayload $part): bool => ! $part->isInline()
            )),
            'sent_at' => $payload->sentAt ?? now(),
        ]);

        $this->storeParts($model, $uuid, $payload->parts);

        return $model;
    }

    /**
     * Reproduces Message::toIterable(), but yielding the prepared headers we
     * already hold rather than generating a second, different set.
     *
     * @return iterable<string>
     */
    protected function rawChunks(RawMessage $message, ?Headers $preparedHeaders): iterable
    {
        if (! $message instanceof Message || ! $preparedHeaders instanceof Headers) {
            return $message->toIterable();
        }

        $body = $message->getBody() ?? new TextPart('');

        return (function () use ($preparedHeaders, $body) {
            yield $preparedHeaders->toString();
            yield from $body->toIterable();
        })();
    }

    protected function payload(RawMessage $message, Envelope $envelope, string $mailer, ?Headers $preparedHeaders): MessagePayload
    {
        $envelopeRecipients = $this->addresses($envelope->getRecipients());
        $envelopeSender = $envelope->getSender()->getAddress();

        if (! $message instanceof Email) {
            return $this->payloadForRawMessage($message, $mailer, $envelopeRecipients, $envelopeSender, $preparedHeaders);
        }

        $headers = $preparedHeaders ?? $message->getHeaders();

        return new MessagePayload(
            mailer: $mailer,
            subject: $message->getSubject(),
            messageId: $this->messageId($headers),
            from: $this->addresses($message->getFrom()),
            to: $this->addresses($message->getTo()),
            cc: $this->addresses($message->getCc()),
            // Read BCC from the Email itself: getPreparedHeaders() strips the
            // Bcc header, because it must never appear in the sent message.
            // That means the exported .eml legitimately will not show it.
            bcc: $this->addresses($message->getBcc()),
            replyTo: $this->addresses($message->getReplyTo()),
            envelopeRecipients: $envelopeRecipients,
            envelopeSender: $envelopeSender,
            htmlBody: $this->stringify($message->getHtmlBody()),
            textBody: $this->stringify($message->getTextBody()),
            headers: $this->headers($headers),
            tags: $this->tags($headers),
            metadata: $this->metadata($headers),
            sentAt: $this->sentAt($headers),
            parts: $this->parts($message),
        );
    }

    /**
     * A message that never went through Laravel's Mailer -- we only have raw
     * MIME to work with, so record what the headers give us and no more.
     *
     * @param  array<int, array{address: string, name: string|null}>  $envelopeRecipients
     */
    protected function payloadForRawMessage(RawMessage $message, string $mailer, array $envelopeRecipients, string $envelopeSender, ?Headers $preparedHeaders): MessagePayload
    {
        $headers = $preparedHeaders ?? ($message instanceof Message ? $message->getHeaders() : null);

        return new MessagePayload(
            mailer: $mailer,
            subject: $headers?->get('Subject')?->getBodyAsString(),
            messageId: $headers instanceof Headers ? $this->messageId($headers) : null,
            from: [],
            to: [],
            cc: [],
            bcc: [],
            replyTo: [],
            envelopeRecipients: $envelopeRecipients,
            envelopeSender: $envelopeSender,
            htmlBody: null,
            textBody: null,
            headers: $headers instanceof Headers ? $this->headers($headers) : [],
            tags: $headers instanceof Headers ? $this->tags($headers) : [],
            metadata: $headers instanceof Headers ? $this->metadata($headers) : [],
            sentAt: $headers instanceof Headers ? $this->sentAt($headers) : null,
            parts: [],
        );
    }

    /**
     * @param  array<int, AttachmentPayload>  $parts
     */
    protected function storeParts(TestMailMessage $model, string $uuid, array $parts): void
    {
        $maxSize = config('test-mail.storage.max_attachment_size');

        foreach ($parts as $index => $part) {
            $withinLimit = $maxSize === null || $part->size() <= (int) $maxSize;

            $model->attachments()->create([
                'filename' => $part->filename,
                'mime_type' => $part->mimeType,
                'size' => $part->size(),
                'disposition' => $part->disposition,
                'content_id' => $part->contentId,
                'path' => $withinLimit
                    ? $this->store->putAttachment($uuid, $index, $part->filename, $part->contents)
                    : null,
            ]);
        }
    }

    /**
     * @return array<int, AttachmentPayload>
     */
    protected function parts(Email $message): array
    {
        return array_map(function (DataPart $part): AttachmentPayload {
            $hasContentId = $part->hasContentId();

            return new AttachmentPayload(
                filename: $part->getFilename(),
                mimeType: $part->getContentType(),
                // embed() marks parts inline; everything else is a real file.
                disposition: $part->getDisposition() ?? ($hasContentId ? 'inline' : 'attachment'),
                contentId: $hasContentId ? $part->getContentId() : null,
                contents: $part->getBody(),
            );
        }, $message->getAttachments());
    }

    /**
     * Laravel always hands us string bodies, but Symfony permits resources.
     * Swap any resource for its contents so the MIME generation below and the
     * database row cannot disagree about what the body was.
     */
    protected function normalizeBodies(RawMessage $message): void
    {
        if (! $message instanceof Email) {
            return;
        }

        if (is_resource($html = $message->getHtmlBody())) {
            $message->html($this->stringify($html), $message->getHtmlCharset() ?: 'utf-8');
        }

        if (is_resource($text = $message->getTextBody())) {
            $message->text($this->stringify($text), $message->getTextCharset() ?: 'utf-8');
        }
    }

    /**
     * @param  resource|string|null  $body
     */
    protected function stringify($body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (! is_resource($body)) {
            return (string) $body;
        }

        if (stream_get_meta_data($body)['seekable'] ?? false) {
            rewind($body);
        }

        return stream_get_contents($body) ?: null;
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, array{address: string, name: string|null}>
     */
    protected function addresses(array $addresses): array
    {
        return array_map(fn (Address $address): array => [
            'address' => $address->getAddress(),
            'name' => $address->getName() !== '' ? $address->getName() : null,
        ], array_values($addresses));
    }

    protected function messageId(Headers $headers): ?string
    {
        $value = $headers->get('Message-ID')?->getBodyAsString();

        return $value === null ? null : trim($value, '<>');
    }

    /**
     * Every header as it will appear on the wire, minus the two kinds we
     * break out into their own columns.
     *
     * @return array<string, string>
     */
    protected function headers(Headers $headers): array
    {
        $collected = [];

        foreach ($headers->all() as $header) {
            if ($header instanceof TagHeader || $header instanceof MetadataHeader) {
                continue;
            }

            $collected[$header->getName()] = $this->headerValue($header);
        }

        return $collected;
    }

    /**
     * @return array<int, string>
     */
    protected function tags(Headers $headers): array
    {
        $tags = [];

        foreach ($headers->all() as $header) {
            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
            }
        }

        return $tags;
    }

    /**
     * @return array<string, string>
     */
    protected function metadata(Headers $headers): array
    {
        $metadata = [];

        foreach ($headers->all() as $header) {
            if ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
            }
        }

        return $metadata;
    }

    protected function sentAt(Headers $headers): ?DateTimeInterface
    {
        $date = $headers->get('Date');

        return $date instanceof DateHeader ? $date->getDateTime() : null;
    }

    protected function headerValue(HeaderInterface $header): string
    {
        try {
            return $header->getBodyAsString();
        } catch (\Throwable) {
            // A malformed header must never cost us the whole message.
            return '';
        }
    }
}
