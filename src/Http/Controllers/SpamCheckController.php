<?php

namespace Ebbbang\Mailroom\Http\Controllers;

use Ebbbang\Mailroom\Exceptions\CannotCheckSpamException;
use Ebbbang\Mailroom\Mailroom;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Spam\SpamChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * Check one message, once, because somebody asked.
 *
 * This is the only place Mailroom sends captured mail anywhere, so it happens
 * on a deliberate click and nowhere else. Opening a message, polling for new
 * mail and listing the mailbox all leave the message where it is.
 *
 * Checking again overwrites the stored result: the point of a second check is
 * usually that the template changed, and two scores for one message would only
 * raise the question of which is current.
 */
class SpamCheckController
{
    public function __invoke(Request $request, MailroomMessage $message, SpamChecker $checker): RedirectResponse
    {
        // Reading the mailbox does not imply permission to hand its contents to
        // a third party. Same separation as forwarding, for the same reason.
        abort_unless(Mailroom::canSpamCheckFrom($request), 403);

        try {
            $result = $checker->check($message);
        } catch (CannotCheckSpamException $e) {
            // Written for the person reading the mailbox, so shown rather than
            // swallowed.
            return back()->with('mailroom.error', $e->getMessage());
        } catch (Throwable $e) {
            return back()->with('mailroom.error', sprintf(
                'Could not check this message: %s', $e->getMessage()
            ));
        }

        $message->forceFill([
            'spam_score' => $result->score,
            'spam_rules' => $result->rules,
            'spam_checked_at' => Date::now(),
        ])->save();

        return back()->with('mailroom.status', sprintf(
            'SpamAssassin scored this message %s.', number_format($result->score, 1)
        ));
    }
}
