<?php

namespace Ebbbang\Mailroom\Http\Controllers;

use Ebbbang\Mailroom\Mailroom;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Models\MailroomRead;
use Ebbbang\Mailroom\Storage\RawMessageStore;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController
{
    public function index(Request $request, ?MailroomMessage $message = null): Renderable
    {
        $search = $request->query('search');
        $mailer = $request->query('mailer');

        // Null when nobody is signed in, which switches read state off
        // wholesale: no marking, no markers, no count.
        $reader = Mailroom::readerFor($request);

        $selected = $message?->exists ? $message->load('attachments') : null;

        /*
         * Opening a message marks it read, and it happens before the list is
         * built so the row you just clicked renders in its new state rather
         * than one page load behind.
         *
         * A GET with a side effect is deliberate here, as it is in any mail
         * client: reading is the act. Polling for new mail hits /recent, which
         * touches none of this, so nothing is ever marked in the background.
         */
        if ($selected !== null && $reader !== null) {
            MailroomRead::markRead([$selected->getKey()], $reader);
        }

        $query = MailroomMessage::query()
            ->search(is_string($search) ? $search : null)
            ->forMailer(is_string($mailer) ? $mailer : null);

        $messages = (clone $query)
            ->when($reader !== null, fn (Builder $builder): Builder => $builder->withExists([
                'reads as is_read' => fn (Builder $reads): Builder => $reads->where('reader_id', $reader),
            ]))
            ->latest('id')
            ->paginate((int) config('mailroom.ui.per_page', 25))
            ->withQueryString();

        return view('mailroom::index', [
            'messages' => $messages,
            'selected' => $selected,
            'search' => $search,
            'mailer' => $mailer,
            'mailers' => $this->availableMailers(),
            'reader' => $reader,

            // Counted on the same filtered query as the list, so the two agree:
            // a count of everything unread would contradict a filtered list.
            'unreadCount' => $reader === null ? null : $this->unreadCount(clone $query, $reader),

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

    protected function unreadCount(Builder $query, string $reader): int
    {
        return $query
            ->whereDoesntHave('reads', fn (Builder $reads): Builder => $reads->where('reader_id', $reader))
            ->count();
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
