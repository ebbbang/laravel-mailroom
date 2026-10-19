<?php

namespace Ebbbang\Mailroom\Http\Controllers;

use Ebbbang\Mailroom\Mailroom;
use Ebbbang\Mailroom\Models\MailroomAddress;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Models\MailroomRead;
use Ebbbang\Mailroom\Storage\RawMessageStore;
use Ebbbang\Mailroom\Support\AddressRole;
use Ebbbang\Mailroom\Support\Mailbox;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MessageController
{
    /**
     * The most people the sidebar lists. A test suite that mints an address
     * per run reaches thousands quickly, and nobody reads that as a list: the
     * sidebar shows the most frequent and a search box finds the rest.
     */
    protected const PEOPLE_SHOWN = 10;

    public function index(Request $request, ?MailroomMessage $message = null): Response
    {
        $state = Mailroom::listState($request);
        $search = $state['search'] ?? null;
        $mailer = $state['mailer'] ?? null;
        $person = $state['person'] ?? null;

        // Null when nobody is signed in, which switches read state off
        // wholesale: no marking, no markers, no count.
        $reader = Mailroom::readerFor($request);

        $box = Mailbox::resolve($state['box'] ?? null, $reader);

        /*
         * Search as you type asks for the list alone. The sidebar's people and
         * counts are most of what this page costs on a large mailbox, and none
         * of them change with a search, so they are left out of that response.
         * So is the open message: a search closes it, so a partial request
         * neither loads it nor marks it read.
         */
        $partial = $request->header('X-Mailroom-Partial') === 'list';

        $selected = ! $partial && $message?->exists ? $message->load('attachments', 'addresses') : null;

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
            ->search($search)
            ->forMailer($mailer)
            ->involving($person)
            /*
             * The open message stays in Unread although opening it just marked
             * it read, as it does in any mail client, until the reader moves
             * on. Dropping it would empty the row out from under the reader,
             * and leave j and k with no current row to move from.
             */
            ->where(fn (Builder $query): Builder => $query
                ->inBox($box, $reader)
                ->when($box === Mailbox::Unread && $selected !== null, fn (Builder $query): Builder => $query->orWhereKey($selected->getKey())))
            ->orderedFor($box)
            /*
             * Only what a row shows: its first recipient, the first envelope
             * recipient for mail with no To, and a count of the rest. A bulk
             * send can carry thousands of Bcc and envelope rows, and loading
             * every one of them on every render would cost more than the
             * page.
             */
            ->with(['addresses' => fn (HasMany $addresses): HasMany => $addresses
                ->where('position', 0)
                ->whereIn('role', [AddressRole::To, AddressRole::EnvelopeTo])])
            ->withCount(['addresses as to_count' => fn (Builder $addresses): Builder => $addresses->where('role', AddressRole::To)])
            ->when($reader !== null, fn (Builder $builder): Builder => $builder->withExists([
                'reads as is_read' => fn (Builder $reads): Builder => $reads->where('reader_id', $reader),
            ]));

        // Read now, so the Unread heading leaves it out of its count and
        // agrees with the sidebar.
        $keptOpen = $box === Mailbox::Unread && $selected !== null && (clone $query)->whereKey($selected->getKey())->exists();

        $messages = $query
            ->paginate((int) config('mailroom.ui.per_page', 25))
            ->withQueryString();

        $list = [
            'messages' => $messages,
            'keptOpen' => $keptOpen,
            'selected' => $selected,
            'search' => $search,
            'mailer' => $mailer,
            'person' => $person,
            'box' => $box,
            'state' => $state,
            'reader' => $reader,
            'mailerCounts' => $this->mailerCounts(),
            'listTitle' => $person ?? $mailer ?? $box->label(),
        ];

        /*
         * The page and the list alone share a URL, told apart by a request
         * header, so both say so in Vary: a cache keyed on the URL would
         * otherwise hand the bare list to Back once a search has put that URL
         * in the address bar. The list alone is not stored at all.
         */
        if ($partial) {
            return response()
                ->view('mailroom::partials.list', $list)
                ->header('Vary', 'X-Mailroom-Partial')
                ->header('Cache-Control', 'no-store, private');
        }

        $peopleSearch = is_string($request->query('people')) ? trim($request->query('people')) : null;

        return response()->view('mailroom::index', [
            ...$list,

            /*
             * The sidebar counts the whole mailbox, not the current filter,
             * the way a mail client's folder counts do. A count that shrank
             * with every search would stop meaning anything; the list header
             * carries the filtered total instead.
             */
            'boxCounts' => $this->boxCounts($reader),
            'people' => MailroomAddress::people(static::PEOPLE_SHOWN, $peopleSearch),
            'peopleTotal' => MailroomAddress::peopleCount($peopleSearch),
            'peopleSearch' => $peopleSearch,

            'pollInterval' => config('mailroom.ui.poll_interval'),

            /*
             * The highest id overall, not the newest on this page. The poll
             * endpoint reports a global maximum, so comparing it against the
             * current page's first row would announce "new mail" the moment you
             * opened page two, or applied any filter that hid the newest
             * message.
             */
            'latestId' => $this->latestId(),
        ])->header('Vary', 'X-Mailroom-Partial');
    }

    /**
     * The sidebar's people, for the search box as it is typed into. Renders
     * the same fragment the page does, so the two cannot drift apart.
     */
    public function people(Request $request): Renderable
    {
        $search = is_string($request->query('q')) ? trim($request->query('q')) : null;

        return view('mailroom::partials.people', [
            'people' => MailroomAddress::people(static::PEOPLE_SHOWN, $search),
            'peopleTotal' => MailroomAddress::peopleCount($search),
            'peopleSearch' => $search,
            'person' => is_string($request->query('person')) ? $request->query('person') : null,
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

    /**
     * Keyed by each mailbox's value.
     *
     * @return array{inbox: int, unread: int|null, 'spam-checked': int}
     */
    protected function boxCounts(?string $reader): array
    {
        $totals = MailroomMessage::query()
            ->toBase()
            ->selectRaw('count(*) as inbox, sum(case when spam_checked_at is not null then 1 else 0 end) as spam_checked')
            ->first();

        return [
            Mailbox::Inbox->value => (int) ($totals->inbox ?? 0),
            Mailbox::Unread->value => $reader === null ? null : MailroomMessage::query()->inBox(Mailbox::Unread, $reader)->count(),
            Mailbox::SpamChecked->value => (int) ($totals->spam_checked ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function mailerCounts(): array
    {
        return MailroomMessage::query()
            ->toBase()
            ->selectRaw('mailer, count(*) as total')
            ->groupBy('mailer')
            ->orderBy('mailer')
            ->pluck('total', 'mailer')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    public function destroy(Request $request, MailroomMessage $message): RedirectResponse
    {
        $message->delete();

        return to_route('mailroom.index', Mailroom::listState($request))
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
}
