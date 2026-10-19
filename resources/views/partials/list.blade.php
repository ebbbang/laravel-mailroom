@php
    use Ebbbang\Mailroom\Spam\SpamResult;
    use Ebbbang\Mailroom\Support\AddressRole;
    use Ebbbang\Mailroom\Support\Avatar;
    use Ebbbang\Mailroom\Support\Highlight;
    use Ebbbang\Mailroom\Support\Mailbox;
    use Illuminate\Support\Arr;

    $total = $messages->total();
    $filtered = filled($search) || filled($mailer) || filled($person);

    $subtitle = match (true) {
        filled($search) => number_format($total).' '.Str::plural('result', $total).' for “'.$search.'”',
        $box === Mailbox::Unread => number_format($total - (int) $keptOpen).' unread',
        $box === Mailbox::SpamChecked => number_format($total).' checked · highest score first',
        default => number_format($total).' '.Str::plural('message', $total),
    };
@endphp

<section class="mr-listcol" aria-label="Message list">
    <div class="mr-list-head">
        <div class="mr-list-heading">
            <div class="mr-list-titles">
                <h2 class="mr-list-title">{{ $listTitle }}</h2>
                <div class="mr-list-sub">{{ $subtitle }}</div>
            </div>

            <button type="button" class="mr-poll" id="mr-poll">
                <span class="mr-dot"></span>
                New mail
            </button>
        </div>

        <form method="GET" action="{{ route('mailroom.index') }}" class="mr-search" role="search">
            {{-- The search replaces itself and starts again from page one;
                 everything else about the list carries through. --}}
            @include('mailroom::partials.list-state-inputs', ['except' => ['search', 'page']])

            <label class="mr-field">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m21 21-4.3-4.3" />
                </svg>
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search subject, body, people"
                    class="mr-input"
                    id="mr-search"
                    aria-label="Search messages"
                    autocomplete="off"
                />
                <kbd class="mr-kbd" aria-hidden="true">/</kbd>
                <span class="mr-spin" aria-hidden="true"></span>

                {{-- Clears the search and keeps the view it was narrowing. A
                     plain link, so it works with scripting off; CSS hides it
                     while the field is empty. --}}
                <a
                    href="{{ route('mailroom.index', Arr::except($state, ['search', 'page'])) }}"
                    class="mr-input-clear"
                    aria-label="Clear search"
                    title="Clear search (Esc)"
                >
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
                </a>
            </label>
        </form>
    </div>

    <nav class="mr-list" aria-label="Captured messages">
        <div class="mr-rows">
            @forelse ($messages as $item)
                @php
                    /*
                     * Rows lead with who the message went to. In a dev
                     * mailbox the sender is nearly always the application
                     * itself, so leading with it would put the same name and
                     * the same avatar on every row; the recipient is what
                     * tells one message from the next.
                     */
                    $lead = $item->addressesFor(AddressRole::To)->first() ?? $item->addressesFor(AddressRole::EnvelopeTo)->first();
                    $avatar = Avatar::for($lead?->address, $lead?->name);
                @endphp

                <a
                    {{-- The whole list state belongs here, or opening a message
                         from page two sends the list back to page one and
                         loses your place. --}}
                    href="{{ route('mailroom.show', ['message' => $item->id] + $state) }}"
                    @class(['mr-item', 'mr-item-read' => $reader !== null && $item->is_read])
                    aria-current="{{ $selected && $selected->is($item) ? 'true' : 'false' }}"
                >
                    @if ($reader !== null && ! $item->is_read)
                        {{-- Announced rather than decorative: the dot is the
                             only thing saying this one has not been read. --}}
                        <span class="mr-unread-dot" role="img" aria-label="Unread"></span>
                    @endif

                    <span
                        class="mr-avatar mr-tone-{{ $avatar->tone }}"
                        aria-hidden="true"
                    >{{ $avatar->initials }}</span>

                    <span class="mr-item-body">
                        <span class="mr-item-top">
                            <span class="mr-item-who">
                                {{ $lead?->name ?: $lead?->address ?: '(no recipient)' }}
                                @if ($item->to_count > 1)
                                    <span class="mr-item-more">+{{ $item->to_count - 1 }}</span>
                                @endif
                            </span>
                            <span class="mr-item-time" title="{{ $item->created_at?->toDayDateTimeString() }}">
                                {{ $item->created_at?->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}
                            </span>
                        </span>

                        <span class="mr-item-subject">{{ Highlight::mark($item->displaySubject(), $search) }}</span>

                        @if (filled($item->preview()))
                            <span class="mr-item-preview">{{ Highlight::mark($item->preview(160), $search) }}</span>
                        @endif

                        @if ($item->hasSpamResult() || $item->attachment_count > 0 || count($mailerCounts) > 1 || filled($item->tags))
                            <span class="mr-item-meta">
                                @if ($item->hasSpamResult())
                                    <span
                                        class="mr-badge mr-badge-{{ SpamResult::band($item->spam_score) }}"
                                        title="SpamAssassin score"
                                    >
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
                                        </svg>
                                        {{ number_format($item->spam_score, 1) }}
                                    </span>
                                @endif

                                @if ($item->attachment_count > 0)
                                    <span class="mr-badge mr-badge-info" title="Attachments">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
                                        </svg>
                                        {{ $item->attachment_count }}
                                    </span>
                                @endif

                                {{-- Wide screens only: both are in the header
                                     when the message is open, and a narrow row
                                     keeps what bears on the message itself. --}}
                                @if (count($mailerCounts) > 1)
                                    <span class="mr-badge mr-badge-wide">{{ $item->mailer }}</span>
                                @endif

                                @foreach (array_slice($item->tags ?? [], 0, 2) as $tag)
                                    <span class="mr-badge mr-badge-accent mr-badge-wide">{{ $tag }}</span>
                                @endforeach
                            </span>
                        @endif
                    </span>
                </a>
            @empty
                <div class="mr-empty">
                    <span class="mr-empty-mark">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M22 12h-6l-2 3h-4l-2-3H2" />
                            <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
                        </svg>
                    </span>

                    @if ($filtered)
                        <div class="mr-empty-title">No matches</div>
                        <div>Nothing captured fits that filter.</div>
                    @elseif ($box === Mailbox::Unread)
                        <div class="mr-empty-title">All caught up</div>
                        <div>Everything here has been read.</div>
                    @elseif ($box === Mailbox::SpamChecked)
                        <div class="mr-empty-title">Nothing checked yet</div>
                        <div>Run a spam check from a message's Spam Check tab and it will be listed here.</div>
                    @else
                        <div class="mr-empty-title">Nothing here yet</div>
                        <div>Captured mail will appear in this list.</div>
                    @endif
                </div>
            @endforelse
        </div>

        @if ($messages->isNotEmpty())
            <div class="mr-list-foot">
                {{--
                    Marking what is on screen, as a plain form. It carries the
                    ids once, and the second button spoofs DELETE rather than
                    repeating a page of hidden fields, which is the same
                    mechanism the Delete button uses.
                --}}
                @if ($reader !== null)
                    <form method="POST" action="{{ route('mailroom.read.page') }}" class="mr-list-actions">
                        @csrf

                        @foreach ($messages as $item)
                            <input type="hidden" name="ids[]" value="{{ $item->id }}" />
                        @endforeach

                        @include('mailroom::partials.list-state-inputs')

                        <button type="submit" class="mr-btn mr-btn-ghost mr-btn-sm">
                            <span class="mr-long">Mark page read</span>
                            <span class="mr-short">Mark read<span class="mr-sr"> (this page)</span></span>
                        </button>
                        <button type="submit" class="mr-btn mr-btn-ghost mr-btn-sm" name="_method" value="DELETE">
                            <span class="mr-long">Mark page unread</span>
                            <span class="mr-short">Mark unread<span class="mr-sr"> (this page)</span></span>
                        </button>
                    </form>
                @endif

                @include('mailroom::partials.clear-all', ['class' => 'mr-clear-narrow'])

                @if ($messages->hasPages())
                    <div class="mr-pager">
                        @if ($messages->onFirstPage())
                            <span class="mr-icon-btn" aria-disabled="true" aria-label="Newer">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
                            </span>
                        @else
                            <a
                                href="{{ $messages->previousPageUrl() }}"
                                class="mr-icon-btn"
                                aria-label="Newer"
                                title="Newer"
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
                            </a>
                        @endif

                        <span>{{ $messages->currentPage() }} / {{ $messages->lastPage() }}</span>

                        @if ($messages->hasMorePages())
                            <a
                                href="{{ $messages->nextPageUrl() }}"
                                class="mr-icon-btn"
                                aria-label="Older"
                                title="Older"
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
                            </a>
                        @else
                            <span class="mr-icon-btn" aria-disabled="true" aria-label="Older">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </nav>
</section>

@push('scripts')
    <script>
        (function () {
            /*
             * Opening a message is a full page load, so the list's own scroll
             * container would otherwise start at the top again.
             *
             * The fix is to *preserve* the offset, not to recompute a nice one.
             * Anything that repositions the list moves the row out from under
             * the pointer, so clicking twice in the same spot hits two
             * different messages. Restoring the exact offset means the list
             * does not appear to move at all.
             */
            /*
             * Keyed on what the list contains, so changing page or filter
             * starts at the top rather than inheriting an unrelated offset.
             * Read from the address bar each time, because search as you type
             * changes it, and swaps in a new list, without a page load.
             */
            function key() {
                var params = new URLSearchParams(location.search);

                return (
                    'mr-list-scroll:' +
                    @json(\Ebbbang\Mailroom\Mailroom::LIST_STATE)
                        .map(function (name) {
                            return params.get(name) || (name === 'page' ? '1' : '');
                        })
                        .join('|')
                );
            }

            function remember(list) {
                try {
                    sessionStorage.setItem(key(), String(list.scrollTop));
                } catch (e) {
                    /* private mode: the list just starts at the top */
                }
            }

            var pending = false;

            // On the document rather than the list, so a list swapped in by a
            // search is remembered too. Scroll does not bubble, but it can be
            // caught on the way down.
            document.addEventListener(
                'scroll',
                function (event) {
                    var list = event.target;

                    if (pending || !(list instanceof Element) || !list.matches('.mr-list')) {
                        return;
                    }

                    pending = true;

                    requestAnimationFrame(function () {
                        pending = false;
                        remember(list);
                    });
                },
                { capture: true, passive: true },
            );

            var list = document.querySelector('.mr-list');

            // On a phone reading a message the list is not on screen at all,
            // and measuring a hidden element would store a meaningless zero.
            if (!list || !list.offsetParent) {
                return;
            }

            try {
                var saved = sessionStorage.getItem(key());

                if (saved !== null) {
                    list.scrollTop = parseFloat(saved) || 0;
                }
            } catch (e) {
                /* ignore */
            }

            /*
             * Only when the selected row is *entirely* out of view -- arriving
             * from a link, or a session with nothing stored. Scrolled to the
             * nearest edge rather than centred, again to move as little as
             * possible. scrollTop is set by hand instead of using
             * scrollIntoView so no ancestor can scroll as a side effect.
             */
            var current = list.querySelector('.mr-item[aria-current="true"]');

            if (current) {
                var listBox = list.getBoundingClientRect();
                var itemBox = current.getBoundingClientRect();

                if (itemBox.bottom <= listBox.top) {
                    list.scrollTop += itemBox.top - listBox.top;
                    remember(list);
                } else if (itemBox.top >= listBox.bottom) {
                    var foot = list.querySelector('.mr-list-foot');

                    // The footer is sticky, so aligning to the true bottom would
                    // tuck the row behind it.
                    list.scrollTop += itemBox.bottom - listBox.bottom + (foot ? foot.offsetHeight : 0);
                    remember(list);
                }
            }
        })();
    </script>
@endpush

@push('scripts')
    <script>
        (function () {
            /*
             * Search as you type. A quarter of a second after the last
             * keystroke, the list for the new search is fetched and swapped in
             * place, and the address bar is updated to match, so a refresh or
             * a shared link shows the same results. replaceState rather than
             * pushState, so Back leaves the page instead of replaying every
             * prefix that was typed.
             *
             * The request asks for the list alone (X-Mailroom-Partial), which
             * spares the server the sidebar's queries. Without scripting, the
             * form submits as an ordinary search.
             */
            var form = document.querySelector('.mr-search');

            if (!form) {
                return;
            }

            var input = document.getElementById('mr-search');
            var field = input.closest('.mr-field');
            var clear = field.querySelector('.mr-input-clear');
            var timer = null;
            var pending = null;

            function target() {
                var params = new URLSearchParams(new FormData(form));

                params.delete('page');

                if (input.value.trim() === '') {
                    params.delete('search');
                }

                var query = params.toString();

                // The mailbox, not the open message: a search closes it.
                return form.getAttribute('action') + (query ? '?' + query : '');
            }

            function swap(fresh, selector) {
                var incoming = fresh.querySelector(selector);
                var current = document.querySelector(selector);

                if (incoming && current) {
                    current.replaceWith(incoming);
                }
            }

            /*
             * Searching closes the open message, as Gmail does. Every form in
             * its toolbar, and its back link, carries the list as it was
             * before the search, so leaving it open would send the reader
             * back to a list no longer on screen.
             */
            function closeMessage() {
                var empty = document.getElementById('mr-reader-empty');
                var reader = document.querySelector('.mr-detail');

                if (!empty || !reader) {
                    return;
                }

                reader.replaceChildren(empty.content.cloneNode(true));
                document.querySelector('.mr-app').classList.remove('mr-reading');
                document.title = 'Mailroom';
            }

            function search() {
                var url = target();

                if (pending) {
                    pending.abort();
                }

                var request = (pending = new AbortController());
                field.classList.add('is-loading');

                fetch(url, { headers: { Accept: 'text/html', 'X-Mailroom-Partial': 'list' }, signal: request.signal })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Search failed: ' + response.status);
                        }

                        return response.text();
                    })
                    .then(function (html) {
                        var fresh = new DOMParser().parseFromString(html, 'text/html');

                        swap(fresh, '.mr-list-sub');
                        swap(fresh, '.mr-list');
                        closeMessage();

                        // The clear link points at this view without the search.
                        var incomingClear = fresh.querySelector('.mr-search .mr-input-clear');

                        if (incomingClear) {
                            clear.setAttribute('href', incomingClear.getAttribute('href'));
                        }

                        history.replaceState(null, '', url);
                    })
                    .catch(function (error) {
                        // An ordinary search renders the same list server side.
                        if (error.name !== 'AbortError') {
                            form.submit();
                        }
                    })
                    .finally(function () {
                        if (pending === request) {
                            pending = null;
                            field.classList.remove('is-loading');
                        }
                    });
            }

            function reset() {
                input.value = '';
                clearTimeout(timer);
                search();
                input.focus();
            }

            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(search, 250);
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                clearTimeout(timer);
                search();
            });

            clear.addEventListener('click', function (event) {
                event.preventDefault();
                reset();
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && input.value !== '') {
                    event.preventDefault();
                    reset();
                }
            });
        })();
    </script>
@endpush

{{-- Guarded, so an empty list ships no shortcuts that have nothing to move
     between. --}}
@if ($messages->isNotEmpty() || $filtered)
    @push('scripts')
        <script>
            (function () {
                /*
                 * j and k move through the list, / jumps to search. Navigation
                 * only: nothing here deletes, marks or sends, so a stray key
                 * can at worst open a different message.
                 *
                 * The rows are read on each key press rather than once, because
                 * search as you type swaps the list in place.
                 */
                var search = document.getElementById('mr-search');

                function isOpen(id) {
                    var element = document.getElementById(id);

                    return element !== null && !element.hidden;
                }

                function typing(target) {
                    if (!(target instanceof Element)) {
                        return false;
                    }

                    return target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName);
                }

                document.addEventListener('keydown', function (event) {
                    if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey) {
                        return;
                    }

                    // The dialogs own the keyboard while they are up.
                    if (typing(event.target) || isOpen('mr-forward') || isOpen('mr-lightbox') || isOpen('mr-confirm')) {
                        return;
                    }

                    if (event.key === '/' && search) {
                        event.preventDefault();
                        search.focus();
                        search.select();
                        return;
                    }

                    if (event.key !== 'j' && event.key !== 'k') {
                        return;
                    }

                    var rows = Array.prototype.slice.call(document.querySelectorAll('.mr-list .mr-item'));

                    var current = rows.findIndex(function (row) {
                        return row.getAttribute('aria-current') === 'true';
                    });

                    // With nothing open, j opens the first row and k does nothing.
                    var target = current === -1 ? (event.key === 'j' ? 0 : -1) : current + (event.key === 'j' ? 1 : -1);

                    if (target >= 0 && target < rows.length) {
                        event.preventDefault();
                        window.location.href = rows[target].href;
                    }
                });
            })();
        </script>
    @endpush
@endif
