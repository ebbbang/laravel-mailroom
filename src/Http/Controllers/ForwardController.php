<?php

namespace Ebbbang\Mailroom\Http\Controllers;

use Ebbbang\Mailroom\Exceptions\CannotForwardException;
use Ebbbang\Mailroom\Forwarding\MessageForwarder;
use Ebbbang\Mailroom\Mailroom;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ForwardController
{
    public function __invoke(Request $request, MailroomMessage $message, MessageForwarder $forwarder): RedirectResponse
    {
        // Reading the mailbox does not imply permission to send from it. See
        // Mailroom::canForwardFrom() for why this is separate from the gate.
        abort_unless(Mailroom::canForwardFrom($request), 403);

        $validated = $request->validate([
            'to' => ['required', 'string', 'email:rfc', 'max:254'],
        ]);

        try {
            $verbatim = $forwarder->forward($message, $validated['to']);
        } catch (CannotForwardException $e) {
            // These carry an explanation written for the person reading the
            // mailbox, so they are shown rather than swallowed.
            return $this->back($request, $message, ['mailroom.error' => $e->getMessage()]);
        } catch (Throwable $e) {
            // Anything else is the relay refusing us -- bad credentials, no
            // route to host, a rejected sender. The message stays captured
            // either way, so this is reported rather than raised.
            return $this->back($request, $message, ['mailroom.error' => sprintf(
                'Could not forward the message: %s', $e->getMessage()
            )]);
        }

        return $this->back($request, $message, ['mailroom.status' => sprintf(
            $verbatim
                ? 'Forwarded to %s exactly as it was sent.'
                : 'Forwarded to %s, with the original recipients kept in the headers.',
            $validated['to']
        )]);
    }

    /**
     * Back to the message, keeping whichever list the reader was looking at.
     *
     * @param  array<string, string>  $flash
     */
    protected function back(Request $request, MailroomMessage $message, array $flash): RedirectResponse
    {
        return redirect()->to(route('mailroom.show', [
            'message' => $message,
            ...$request->only('search', 'mailer', 'page'),
        ]))->with($flash);
    }
}
