<?php

namespace Ebbbang\Mailroom\Http\Controllers;

use Ebbbang\Mailroom\Mailroom;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Models\MailroomRead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Marking mail read and unread, one message at a time or a whole page at once.
 *
 * Opening a message already marks it read, so these routes exist for the other
 * direction: putting something back on the pile, and clearing a page you have
 * decided you do not need to look at.
 *
 * Every action needs a signed-in reader and refuses without one, rather than
 * quietly doing nothing. The mailbox never renders these controls when nobody
 * is signed in, so arriving here without a reader means something else is going
 * on, and a silent success would hide it.
 */
class ReadController
{
    public function store(Request $request, MailroomMessage $message): RedirectResponse
    {
        $reader = $this->reader($request);

        MailroomRead::markRead([$message->getKey()], $reader);

        return $this->backToList($request);
    }

    public function destroy(Request $request, MailroomMessage $message): RedirectResponse
    {
        $reader = $this->reader($request);

        MailroomRead::markUnread([$message->getKey()], $reader);

        return $this->backToList($request);
    }

    public function storeMany(Request $request): RedirectResponse
    {
        $reader = $this->reader($request);

        MailroomRead::markRead($this->messageIds($request), $reader);

        return $this->backToList($request);
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $reader = $this->reader($request);

        MailroomRead::markUnread($this->messageIds($request), $reader);

        return $this->backToList($request);
    }

    protected function reader(Request $request): string
    {
        $reader = Mailroom::readerFor($request);

        abort_if($reader === null, 403, 'Marking mail read needs a signed-in user.');

        return $reader;
    }

    /**
     * The ids the page-wide buttons submit, one hidden field per row on screen.
     *
     * @return array<int, int>
     */
    protected function messageIds(Request $request): array
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        return array_map(intval(...), $validated['ids']);
    }

    /**
     * Back to the list, never to the message.
     *
     * Opening a message marks it read, so a "Mark unread" that returned you to
     * it would undo itself on arrival.
     *
     * Only the keys the list is built from are carried across, and blank ones
     * are dropped so the URL stays clean. Taking a redirect target from the
     * form instead would let a hand-made one bounce someone elsewhere.
     */
    protected function backToList(Request $request): RedirectResponse
    {
        return to_route('mailroom.index', array_filter(
            $request->only('search', 'mailer', 'page'),
            filled(...)
        ));
    }
}
