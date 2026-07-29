<?php

namespace Ebbbang\TestMail\Http\Controllers;

use Ebbbang\TestMail\Models\TestMailMessage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController
{
    public function __invoke(TestMailMessage $message, int $attachment): StreamedResponse
    {
        // Resolved through the relation rather than by id alone, so an
        // attachment id from one message cannot be fetched via another.
        $part = $message->attachments()->findOrFail($attachment);

        abort_unless($part->hasContents(), 404);

        return response()->stream(function () use ($part): void {
            $stream = $part->readStream();

            if ($stream === null) {
                return;
            }

            fpassthru($stream);
            fclose($stream);
        }, 200, [
            /*
             * Deliberately octet-stream and never the part's own MIME type.
             * Serving an emailed .svg or .html back with its declared type
             * would execute it on this application's origin; forcing a
             * download keeps an attachment an attachment.
             */
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => sprintf(
                'attachment; filename="%s"',
                Str::limit(str_replace('"', '', $part->displayName()), 100, '')
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Length' => (string) $part->size,
        ]);
    }
}
