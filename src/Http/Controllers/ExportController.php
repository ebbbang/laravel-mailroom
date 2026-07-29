<?php

namespace Ebbbang\TestMail\Http\Controllers;

use Ebbbang\TestMail\Models\TestMailMessage;
use Ebbbang\TestMail\Support\CidInliner;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController
{
    public function __invoke(TestMailMessage $message, string $format, CidInliner $inliner): Response|StreamedResponse
    {
        return match ($format) {
            'eml' => $this->eml($message),
            'html' => $this->html($message, $inliner),
            default => abort(404),
        };
    }

    /**
     * The stored MIME verbatim, which opens directly in Mail.app,
     * Thunderbird or Outlook.
     *
     * Note this will not contain a Bcc header: Symfony strips Bcc when
     * rendering a message, since it must never travel with it. The mailbox UI
     * shows the recorded BCC recipients separately.
     */
    protected function eml(TestMailMessage $message): StreamedResponse
    {
        abort_unless($message->hasRaw(), 404);

        return response()->stream(function () use ($message): void {
            $stream = $message->rawStream();

            if ($stream === null) {
                return;
            }

            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => 'message/rfc822',
            'Content-Disposition' => $this->disposition($message, 'eml'),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * A standalone HTML file with embedded images inlined as data: URIs, so
     * it renders correctly away from this mailbox.
     *
     * Always sent as a download rather than rendered: serving email HTML
     * inline on the application's own origin would be a stored-XSS sink.
     */
    protected function html(TestMailMessage $message, CidInliner $inliner): Response
    {
        $html = $inliner->inline($message, $message->html_body);

        abort_if(blank($html), 404, 'This message has no HTML body.');

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => $this->disposition($message, 'html'),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function disposition(TestMailMessage $message, string $extension): string
    {
        $slug = Str::slug((string) $message->subject) ?: 'message';

        return sprintf('attachment; filename="%s-%d.%s"', Str::limit($slug, 60, ''), $message->id, $extension);
    }
}
