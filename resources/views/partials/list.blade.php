@php use Ebbbang\TestMail\Models\TestMailMessage as Msg; @endphp

<nav class="tm-list" aria-label="Captured messages">
    @forelse ($messages as $item)
        <a
            {{-- "page" belongs here too, or opening a message from page two
                 sends the list back to page one and loses your place. --}}
            href="{{ route('test-mail.show', ['message' => $item->id] + request()->only('search', 'mailer', 'page')) }}"
            class="tm-item"
            aria-current="{{ $selected && $selected->is($item) ? 'true' : 'false' }}"
        >
            <div class="tm-item-top">
                <span class="tm-item-subject">{{ $item->displaySubject() }}</span>
                <span class="tm-item-time" title="{{ $item->created_at?->toDayDateTimeString() }}">
                    {{ $item->created_at?->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}
                </span>
            </div>

            <div class="tm-item-to">{{ Msg::formatAddressList($item->to) ?: '—' }}</div>

            @if (filled($item->preview()))
                <div class="tm-item-preview">{{ $item->preview(90) }}</div>
            @endif

            @if ($item->attachment_count > 0 || count($mailers) > 1 || filled($item->tags))
                <div class="tm-item-meta">
                    @if ($item->attachment_count > 0)
                        <span class="tm-badge">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                            </svg>
                            {{ $item->attachment_count }}
                        </span>
                    @endif

                    @if (count($mailers) > 1)
                        <span class="tm-badge">{{ $item->mailer }}</span>
                    @endif

                    @foreach (array_slice($item->tags ?? [], 0, 2) as $tag)
                        <span class="tm-badge tm-badge-accent">{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
        </a>
    @empty
        <div class="tm-empty">
            <span class="tm-empty-mark">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
                </svg>
            </span>

            @if (filled($search) || filled($mailer))
                <div class="tm-empty-title">No matches</div>
                <div>Nothing captured fits that filter.</div>
            @else
                <div class="tm-empty-title">Nothing here yet</div>
                <div>Captured mail will appear in this list.</div>
            @endif
        </div>
    @endforelse

    @if ($messages->hasPages())
        <div class="tm-pager">
            @if ($messages->onFirstPage())
                <span class="tm-btn" aria-disabled="true">Newer</span>
            @else
                <a href="{{ $messages->previousPageUrl() }}" class="tm-btn">Newer</a>
            @endif

            <span class="tm-item-time">{{ $messages->currentPage() }} / {{ $messages->lastPage() }}</span>

            @if ($messages->hasMorePages())
                <a href="{{ $messages->nextPageUrl() }}" class="tm-btn">Older</a>
            @else
                <span class="tm-btn" aria-disabled="true">Older</span>
            @endif
        </div>
    @endif
</nav>

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
            var list = document.querySelector('.tm-list');

            if (!list) {
                return;
            }

            // Keyed on what the list contains, so changing page or filter
            // starts at the top rather than inheriting an unrelated offset.
            var params = new URLSearchParams(location.search);
            var key = 'tm-list-scroll:' + [
                params.get('page') || '1',
                params.get('search') || '',
                params.get('mailer') || '',
            ].join('|');

            function remember() {
                try {
                    sessionStorage.setItem(key, String(list.scrollTop));
                } catch (e) { /* private mode: the list just starts at the top */ }
            }

            try {
                var saved = sessionStorage.getItem(key);

                if (saved !== null) {
                    list.scrollTop = parseFloat(saved) || 0;
                }
            } catch (e) { /* ignore */ }

            /*
             * Only when the selected row is *entirely* out of view -- arriving
             * from a link, or a session with nothing stored. Scrolled to the
             * nearest edge rather than centred, again to move as little as
             * possible. scrollTop is set by hand instead of using
             * scrollIntoView so no ancestor can scroll as a side effect.
             */
            var current = list.querySelector('.tm-item[aria-current="true"]');

            if (current) {
                var listBox = list.getBoundingClientRect();
                var itemBox = current.getBoundingClientRect();

                if (itemBox.bottom <= listBox.top) {
                    list.scrollTop += itemBox.top - listBox.top;
                    remember();
                } else if (itemBox.top >= listBox.bottom) {
                    var pager = list.querySelector('.tm-pager');

                    // The pager is sticky, so aligning to the true bottom would
                    // tuck the row behind it.
                    list.scrollTop += (itemBox.bottom - listBox.bottom) + (pager ? pager.offsetHeight : 0);
                    remember();
                }
            }

            var pending = false;

            list.addEventListener('scroll', function () {
                if (pending) {
                    return;
                }

                pending = true;

                requestAnimationFrame(function () {
                    pending = false;
                    remember();
                });
            }, { passive: true });
        })();
    </script>
@endpush
