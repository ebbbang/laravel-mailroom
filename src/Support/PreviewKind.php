<?php

namespace Ebbbang\Mailroom\Support;

/**
 * How a given attachment can be shown in the mailbox.
 *
 * Kinds split into two families. The binary ones are streamed to the browser
 * by AttachmentPreviewController and rendered by a native element. The rest
 * are read and escaped into Blade server-side, so their bytes are never
 * served with a content type at all -- which removes an entire class of
 * problem for anything text-shaped.
 */
enum PreviewKind: string
{
    case Image = 'image';
    case Svg = 'svg';
    case Pdf = 'pdf';
    case Audio = 'audio';
    case Video = 'video';

    case Text = 'text';
    case Csv = 'csv';
    case Json = 'json';
    case Code = 'code';
    case Calendar = 'calendar';
    case Message = 'message';

    case None = 'none';

    /**
     * Whether the raw bytes have to reach the browser for this kind to
     * render, which is what requires the hardened preview route.
     */
    public function servesBytes(): bool
    {
        return match ($this) {
            self::Image, self::Svg, self::Pdf, self::Audio, self::Video => true,
            default => false,
        };
    }

    /**
     * Whether this kind is rendered from text read server-side.
     */
    public function isTextual(): bool
    {
        return match ($this) {
            self::Text, self::Csv, self::Json, self::Code, self::Calendar, self::Message => true,
            default => false,
        };
    }

    /**
     * Media kinds need HTTP Range support or seeking is broken.
     */
    public function isMedia(): bool
    {
        return $this === self::Audio || $this === self::Video;
    }

    public function isPreviewable(): bool
    {
        return $this !== self::None;
    }

    /**
     * A short label for the "no preview" and header states.
     */
    public function label(): string
    {
        return match ($this) {
            self::Image, self::Svg => 'Image',
            self::Pdf => 'PDF',
            self::Audio => 'Audio',
            self::Video => 'Video',
            self::Text => 'Text',
            self::Csv => 'Table',
            self::Json => 'JSON',
            self::Code => 'Source',
            self::Calendar => 'Calendar invite',
            self::Message => 'Email message',
            self::None => 'File',
        };
    }
}
