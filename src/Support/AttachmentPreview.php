<?php

namespace Ebbbang\TestMail\Support;

use Ebbbang\TestMail\Models\TestMailAttachment;

/**
 * Decides how an attachment may be previewed, and -- for the kinds whose
 * bytes reach the browser -- which content type we are willing to serve.
 *
 * The load-bearing rule of this class:
 *
 *   The content type we emit always comes from the allowlist below, never
 *   from the attachment's own mime_type column.
 *
 * That column is filled in by whoever composed the mail, so it is untrusted
 * input. Treating it as a lookup key rather than a value means a claimed type
 * can only ever *select* a known-safe entry or fall through to None -- it can
 * never dictate what we send. An attachment claiming "text/html" therefore
 * cannot get itself rendered as HTML on this application's origin; it lands
 * in Code and is shown as escaped source instead.
 */
final readonly class AttachmentPreview
{
    /**
     * Bytes read when sniffing a file whose declared type and extension both
     * tell us nothing. Enough for any magic number worth recognising.
     */
    private const SNIFF_BYTES = 4096;

    public function __construct(
        public PreviewKind $kind,
        public ?string $contentType = null,
    ) {}

    public static function for(TestMailAttachment $attachment): self
    {
        $preview = self::resolve($attachment->mime_type, $attachment->filename);

        /*
         * Plenty of mailers attach everything as application/octet-stream. If
         * the declared type and the extension are both useless we can still
         * often recognise the file from its leading bytes. This is the only
         * path that touches the disk during resolution, so it stays rare.
         */
        if ($preview->kind === PreviewKind::None
            && self::isUninformative($attachment->mime_type)
            && extension_loaded('fileinfo')
            && $attachment->hasContents()) {
            $sniffed = self::sniff($attachment);

            if ($sniffed !== null) {
                return self::resolve($sniffed, $attachment->filename);
            }
        }

        return $preview;
    }

    /**
     * Resolve from a declared type and filename alone, without any disk access.
     */
    public static function resolve(?string $mimeType, ?string $filename): self
    {
        if (($entry = self::lookup(self::normalize($mimeType))) !== null) {
            return new self($entry[0], $entry[1]);
        }

        $extension = self::extension($filename);

        if ($extension !== null
            && ($mapped = self::mimeForExtension($extension)) !== null
            && ($entry = self::lookup($mapped)) !== null) {
            return new self($entry[0], $entry[1]);
        }

        return new self(PreviewKind::None);
    }

    /**
     * A filename safe to put in a Content-Disposition header.
     */
    public function dispositionFilename(TestMailAttachment $attachment): string
    {
        return mb_substr(str_replace(['"', "\r", "\n"], '', $attachment->displayName()), 0, 100);
    }

    /**
     * The allowlist. Keyed by canonical type, so aliases collapse on the way in.
     *
     * @return array{0: PreviewKind, 1: string|null}|null
     */
    private static function lookup(?string $mime): ?array
    {
        if ($mime === null) {
            return null;
        }

        return match ($mime) {
            // Raster formats every current browser can draw. Deliberately no
            // tiff or heic: they would render as a broken image in Chrome and
            // a working one in Safari, which is worse than offering nothing.
            'image/png' => [PreviewKind::Image, 'image/png'],
            'image/jpeg' => [PreviewKind::Image, 'image/jpeg'],
            'image/gif' => [PreviewKind::Image, 'image/gif'],
            'image/webp' => [PreviewKind::Image, 'image/webp'],
            'image/avif' => [PreviewKind::Image, 'image/avif'],
            'image/bmp' => [PreviewKind::Image, 'image/bmp'],
            'image/x-icon' => [PreviewKind::Image, 'image/x-icon'],

            // Only ever rendered through <img>, where the SVG cannot script
            // or fetch anything. The route's CSP sandbox covers the case of
            // someone opening the URL directly.
            'image/svg+xml' => [PreviewKind::Svg, 'image/svg+xml'],

            'application/pdf' => [PreviewKind::Pdf, 'application/pdf'],

            'audio/mpeg' => [PreviewKind::Audio, 'audio/mpeg'],
            'audio/wav' => [PreviewKind::Audio, 'audio/wav'],
            'audio/ogg' => [PreviewKind::Audio, 'audio/ogg'],
            'audio/mp4' => [PreviewKind::Audio, 'audio/mp4'],
            'audio/aac' => [PreviewKind::Audio, 'audio/aac'],
            'audio/flac' => [PreviewKind::Audio, 'audio/flac'],

            'video/mp4' => [PreviewKind::Video, 'video/mp4'],
            'video/webm' => [PreviewKind::Video, 'video/webm'],
            'video/ogg' => [PreviewKind::Video, 'video/ogg'],
            'video/quicktime' => [PreviewKind::Video, 'video/quicktime'],

            // Everything below is read and escaped server-side, so no content
            // type is ever negotiated for it.
            'text/plain' => [PreviewKind::Text, null],
            'text/markdown' => [PreviewKind::Text, null],

            'text/csv' => [PreviewKind::Csv, null],
            'text/tab-separated-values' => [PreviewKind::Csv, null],

            'application/json' => [PreviewKind::Json, null],

            // Shown as source, never rendered.
            'text/html' => [PreviewKind::Code, null],
            'application/xhtml+xml' => [PreviewKind::Code, null],
            'application/xml' => [PreviewKind::Code, null],
            'application/yaml' => [PreviewKind::Code, null],
            'text/javascript' => [PreviewKind::Code, null],
            'text/css' => [PreviewKind::Code, null],
            'application/sql' => [PreviewKind::Code, null],

            'text/calendar' => [PreviewKind::Calendar, null],
            'message/rfc822' => [PreviewKind::Message, null],

            default => null,
        };
    }

    /**
     * Fold the many spellings of a type onto one canonical spelling.
     */
    private static function normalize(?string $mimeType): ?string
    {
        if ($mimeType === null || trim($mimeType) === '') {
            return null;
        }

        // Drop parameters such as "; charset=utf-8".
        $mime = strtolower(trim(explode(';', $mimeType, 2)[0]));

        return match ($mime) {
            'image/jpg', 'image/pjpeg' => 'image/jpeg',
            'image/vnd.microsoft.icon', 'image/ico', 'image/icon' => 'image/x-icon',
            'image/svg' => 'image/svg+xml',
            'audio/mp3', 'audio/mpeg3', 'audio/x-mpeg-3' => 'audio/mpeg',
            'audio/x-wav', 'audio/wave', 'audio/vnd.wave' => 'audio/wav',
            'audio/m4a', 'audio/x-m4a' => 'audio/mp4',
            'audio/x-flac' => 'audio/flac',
            'video/x-m4v' => 'video/mp4',
            'text/json' => 'application/json',
            'text/xml' => 'application/xml',
            'text/yaml', 'text/x-yaml', 'application/x-yaml' => 'application/yaml',
            'application/javascript', 'application/x-javascript' => 'text/javascript',
            'text/x-sql', 'text/sql' => 'application/sql',
            'text/x-markdown', 'text/x-md' => 'text/markdown',
            'text/log', 'text/x-log' => 'text/plain',
            default => $mime,
        };
    }

    private static function mimeForExtension(string $extension): ?string
    {
        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',

            'pdf' => 'application/pdf',

            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'oga' => 'audio/ogg',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',

            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'mov' => 'video/quicktime',

            'txt', 'log', 'text' => 'text/plain',
            'md', 'markdown' => 'text/markdown',
            'csv' => 'text/csv',
            'tsv' => 'text/tab-separated-values',
            'json' => 'application/json',

            'html', 'htm' => 'text/html',
            'xhtml' => 'application/xhtml+xml',
            'xml' => 'application/xml',
            'yml', 'yaml' => 'application/yaml',
            'js' => 'text/javascript',
            'css' => 'text/css',
            'sql' => 'application/sql',

            'ics', 'ical' => 'text/calendar',
            'eml' => 'message/rfc822',

            // Office formats and archives resolve to nothing on purpose: they
            // cannot be rendered locally without a service we are unable to
            // reach from a mailbox on localhost. See the README.
            default => null,
        };
    }

    private static function extension(?string $filename): ?string
    {
        if ($filename === null || ! str_contains($filename, '.')) {
            return null;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $extension === '' ? null : $extension;
    }

    /**
     * Whether a declared type tells us nothing useful.
     */
    private static function isUninformative(?string $mimeType): bool
    {
        $mime = self::normalize($mimeType);

        return $mime === null
            || in_array($mime, [
                'application/octet-stream',
                'binary/octet-stream',
                'application/unknown',
                'application/force-download',
            ], true);
    }

    private static function sniff(TestMailAttachment $attachment): ?string
    {
        $stream = $attachment->readStream();

        if ($stream === null) {
            return null;
        }

        $sample = fread($stream, self::SNIFF_BYTES);
        fclose($stream);

        if ($sample === false || $sample === '') {
            return null;
        }

        $detected = @finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $sample);

        return $detected === false ? null : $detected;
    }
}
