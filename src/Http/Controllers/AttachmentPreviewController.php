<?php

namespace Ebbbang\TestMail\Http\Controllers;

use Ebbbang\TestMail\Models\TestMailAttachment;
use Ebbbang\TestMail\Models\TestMailMessage;
use Ebbbang\TestMail\Support\PreviewKind;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves attachment bytes for inline preview.
 *
 * This is deliberately a separate route from AttachmentController, which
 * forces application/octet-stream and must keep doing so. Previewing needs
 * real content types, so the safety has to come from somewhere else:
 *
 *  - The content type is taken from AttachmentPreview's allowlist, never from
 *    the attachment's own mime_type column, so untrusted input can only pick
 *    a known-safe entry or be refused outright.
 *  - Content-Security-Policy: sandbox blocks script execution and forces an
 *    opaque origin. That matters for the case the <img> element does not cover
 *    -- somebody copying this URL and opening it directly, which creates a
 *    real document context. Without allow-scripts an SVG cannot run anything,
 *    and without allow-same-origin it can reach no cookies or storage.
 *  - Only image, svg, pdf, audio and video reach this route at all. Anything
 *    text-shaped, .html included, is escaped into Blade server-side and never
 *    served with a content type.
 */
class AttachmentPreviewController
{
    /** Bytes per chunk while streaming. */
    protected const CHUNK = 262144;

    public function __invoke(Request $request, TestMailMessage $message, int $attachment): StreamedResponse
    {
        // Through the relation, so an attachment id from one message cannot be
        // fetched via another.
        $part = $message->attachments()->findOrFail($attachment);

        abort_unless($part->isPreviewable(), 404);

        $preview = $part->preview();

        abort_unless($preview->kind->servesBytes() && $preview->contentType !== null, 404);

        $headers = [
            'Content-Type' => $preview->contentType,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => sprintf('inline; filename="%s"', $preview->dispositionFilename($part)),
            'Referrer-Policy' => 'no-referrer',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Accept-Ranges' => 'bytes',
        ] + $this->policyFor($preview->kind);

        $range = $this->range($request, $part->size);

        // A Range we cannot satisfy has to say so explicitly rather than
        // silently returning the whole file.
        if ($range === false) {
            return response()->stream(fn (): null => null, 416, $headers + [
                'Content-Range' => sprintf('bytes */%d', $part->size),
            ]);
        }

        if ($range === null) {
            return response()->stream(
                fn () => $this->emit($part, 0, $part->size),
                200,
                $headers + ['Content-Length' => (string) $part->size],
            );
        }

        [$start, $end] = $range;
        $length = $end - $start + 1;

        return response()->stream(
            fn () => $this->emit($part, $start, $length),
            206,
            $headers + [
                'Content-Length' => (string) $length,
                'Content-Range' => sprintf('bytes %d-%d/%d', $start, $end, $part->size),
            ],
        );
    }

    /**
     * The content policy for a kind.
     *
     * Everything except PDF gets a full sandbox: no allow-scripts, so an SVG
     * cannot execute even when the URL is opened directly, and no
     * allow-same-origin, so it sits in an opaque origin with no access to
     * cookies or storage.
     *
     * PDF is a deliberate exception. Chrome renders PDFs by building a
     * wrapper document that loads the viewer extension in a cross-process
     * iframe, and a sandbox on the response blocks that extension's own
     * scripts -- the PDF then does not render at all, rather than rendering
     * without script. See https://crbug.com/413851 and
     * https://issues.chromium.org/issues/40328564. Sending no policy is also
     * what GitLab settled on for the same reason.
     *
     * Little is given up by that. A page-level CSP never governed PDF
     * JavaScript in the first place -- that runs inside PDFium's own sandbox,
     * where it cannot reach this page's DOM, cookies or storage. What still
     * protects the response is that the type is pinned by our allowlist, that
     * nosniff prevents it being reinterpreted as anything else, and that
     * Cross-Origin-Resource-Policy keeps other sites from embedding it.
     *
     * @return array<string, string>
     */
    protected function policyFor(PreviewKind $kind): array
    {
        if ($kind === PreviewKind::Pdf) {
            return [];
        }

        return [
            'Content-Security-Policy' => "default-src 'none'; sandbox; base-uri 'none'; form-action 'none'",
        ];
    }

    /**
     * Parse a single-range request.
     *
     * Media seeking depends on this: without 206 responses a browser cannot
     * jump around an audio or video file, and the player looks broken.
     *
     * @return array{0: int, 1: int}|null|false Offsets, null for no range, false for unsatisfiable
     */
    protected function range(Request $request, int $size): array|null|false
    {
        $header = $request->headers->get('Range');

        if ($header === null || $size === 0) {
            return null;
        }

        if (! preg_match('/^bytes=(\d*)-(\d*)$/i', trim($header), $matches)) {
            // Multi-range and unknown units are not worth supporting here;
            // ignoring the header and sending the whole body is legal.
            return null;
        }

        [, $first, $last] = $matches;

        if ($first === '' && $last === '') {
            return false;
        }

        if ($first === '') {
            // Suffix form: the final N bytes.
            $length = min((int) $last, $size);

            return $length === 0 ? false : [$size - $length, $size - 1];
        }

        $start = (int) $first;

        if ($start >= $size) {
            return false;
        }

        $end = $last === '' ? $size - 1 : min((int) $last, $size - 1);

        return $end < $start ? false : [$start, $end];
    }

    /**
     * Stream $length bytes starting at $start.
     */
    protected function emit(TestMailAttachment $part, int $start, int $length): void
    {
        $stream = $part->readStream();

        if ($stream === null) {
            return;
        }

        try {
            if ($start > 0) {
                $this->seek($stream, $start);
            }

            $remaining = $length;

            while ($remaining > 0 && ! feof($stream)) {
                $chunk = fread($stream, min(self::CHUNK, $remaining));

                if ($chunk === false || $chunk === '') {
                    break;
                }

                echo $chunk;

                $remaining -= strlen($chunk);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Not every adapter hands back a seekable stream, so fall back to reading
     * and discarding.
     *
     * @param  resource  $stream
     */
    protected function seek($stream, int $offset): void
    {
        if ((stream_get_meta_data($stream)['seekable'] ?? false) && fseek($stream, $offset) === 0) {
            return;
        }

        $discarded = 0;

        while ($discarded < $offset && ! feof($stream)) {
            $chunk = fread($stream, min(self::CHUNK, $offset - $discarded));

            if ($chunk === false || $chunk === '') {
                break;
            }

            $discarded += strlen($chunk);
        }
    }
}
