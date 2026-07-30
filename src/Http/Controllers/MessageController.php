<?php

namespace Ebbbang\Mailroom\Http\Controllers;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Storage\RawMessageStore;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController
{
    public function index(Request $request, ?MailroomMessage $message = null): Renderable
    {
        $search = $request->query('search');
        $mailer = $request->query('mailer');

        $messages = MailroomMessage::query()
            ->search(is_string($search) ? $search : null)
            ->forMailer(is_string($mailer) ? $mailer : null)
            ->latest('id')
            ->paginate((int) config('mailroom.ui.per_page', 25))
            ->withQueryString();

        $selected = $message?->exists ? $message->load('attachments') : null;

        return view('mailroom::index', [
            'messages' => $messages,
            'selected' => $selected,
            'search' => $search,
            'mailer' => $mailer,
            'mailers' => $this->availableMailers(),
            'pollInterval' => config('mailroom.ui.poll_interval'),

            /*
             * The highest id overall, not the newest on this page. The poll
             * endpoint reports a global maximum, so comparing it against the
             * current page's first row would announce "new mail" the moment you
             * opened page two, or applied any filter that hid the newest
             * message.
             */
            'latestId' => $this->latestId(),
        ]);
    }

    /**
     * Lightweight endpoint the mailbox polls to notice new mail without
     * reloading the page.
     */
    public function recent(Request $request): JsonResponse
    {
        return new JsonResponse([
            'latest_id' => $this->latestId(),
            'count' => MailroomMessage::query()->count(),
        ]);
    }

    protected function latestId(): ?int
    {
        $latest = MailroomMessage::query()->max('id');

        return $latest === null ? null : (int) $latest;
    }

    public function destroy(MailroomMessage $message): RedirectResponse
    {
        $message->delete();

        return to_route('mailroom.index')
            ->with('mailroom.status', 'Message deleted.');
    }

    public function clear(RawMessageStore $store): RedirectResponse
    {
        // Routed through model deletes so each message's blobs go with it,
        // then a directory sweep to catch anything left behind.
        MailroomMessage::query()->orderBy('id')->chunkById(500, function ($messages): void {
            foreach ($messages as $message) {
                $message->delete();
            }
        });

        $store->flush();

        return to_route('mailroom.index')
            ->with('mailroom.status', 'Mailbox cleared.');
    }

    /**
     * @return array<int, string>
     */
    protected function availableMailers(): array
    {
        return MailroomMessage::query()
            ->distinct()
            ->orderBy('mailer')
            ->pluck('mailer')
            ->all();
    }
}
