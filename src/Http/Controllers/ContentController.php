<?php

namespace Ebbbang\Mailroom\Http\Controllers;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Support\CidInliner;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a message body for display inside the mailbox's preview frame.
 *
 * Email HTML is attacker-controlled as far as this application is concerned,
 * so it is never rendered into the mailbox page itself. It is served from
 * here into an <iframe sandbox> that carries neither allow-scripts nor
 * allow-same-origin, which puts it in an opaque origin that cannot script,
 * cannot reach the parent document, and cannot touch session cookies. The
 * CSP below is the second layer in case the frame markup is ever loosened.
 */
class ContentController
{
    public function __invoke(MailroomMessage $message, string $format, CidInliner $inliner): Response|StreamedResponse
    {
        return match ($format) {
            'html' => $this->html($message, $inliner),
            'text' => $this->text($message),
            'raw' => $this->raw($message),
            default => abort(404),
        };
    }

    protected function html(MailroomMessage $message, CidInliner $inliner): Response
    {
        $html = $inliner->inline($message, $message->html_body);

        if (blank($html)) {
            $html = '<!doctype html><meta charset="utf-8">'
                .'<p style="font:14px system-ui,sans-serif;color:#71717a;padding:1rem">'
                .'This message has no HTML body.</p>';
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => "default-src 'none'; img-src data: https:; style-src 'unsafe-inline'; font-src data:; base-uri 'none'; form-action 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    protected function text(MailroomMessage $message): Response
    {
        return response((string) ($message->text_body ?? ''), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function raw(MailroomMessage $message): StreamedResponse
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
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
