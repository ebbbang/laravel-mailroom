@php
    use Ebbbang\Mailroom\Support\Mailbox;
    use Illuminate\Support\Arr;

    /*
     * The sidebar shows one view at a time, the way folders do in a mail
     * client: each link is that view alone, so choosing a mailbox, a person
     * or a mailer replaces whatever was chosen before, search included. Search
     * narrows the view that is showing; it does not follow you to the next.
     */
    $link = fn (array $view): string => route('mailroom.index', array_filter($view));

    $boxLink = fn (Mailbox $target): string => $link(['box' => $target->queryValue()]);

    // A person or a mailer narrows the list further than a mailbox does, so
    // when either is chosen it is the current item, not the mailbox under it.
    $current = match (true) {
        filled($person) => 'person',
        filled($mailer) => 'mailer',
        default => 'box',
    };

    /*
     * The rail's stand-in for a mailer's name: its first letter and the next
     * consonant, so "mailroom" and "marketing" come out as Ml and Mr rather
     * than as two identical Ms.
     */
    $mailerMark = function (string $name): string {
        $letters = preg_replace('/[^a-z]/i', '', $name) ?: '?';
        $second = preg_match('/[^aeiou]/i', substr($letters, 1), $found) ? $found[0] : '';

        return strtoupper($letters[0]).strtolower($second);
    };
@endphp

<nav class="mr-side" aria-label="Mailboxes">
    <a href="{{ route('mailroom.index') }}" class="mr-brand" aria-label="Mailroom">
        <span class="mr-mark">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="4" width="20" height="16" rx="2" />
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
            </svg>
        </span>
        <span class="mr-brand-name">Mailroom</span>
    </a>

    <div class="mr-nav mr-boxes">
        <a
            href="{{ $boxLink(Mailbox::Inbox) }}"
            class="mr-nav-link"
            title="{{ Mailbox::Inbox->label() }}"
            @if ($current === 'box' && $box === Mailbox::Inbox) aria-current="page" @endif
        >
            <svg class="mr-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 12h-6l-2 3h-4l-2-3H2" />
                <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
            </svg>
            <span class="mr-nav-label">{{ Mailbox::Inbox->label() }}</span>
            <span class="mr-badge">{{ number_format($boxCounts['inbox']) }}</span>
        </a>

        {{-- Only with someone signed in. Read state belongs to a person, and
             with nobody to attribute it to there is nothing to list. --}}
        @if ($reader !== null)
            <a
                href="{{ $boxLink(Mailbox::Unread) }}"
                class="mr-nav-link"
                title="{{ Mailbox::Unread->label() }}"
                @if ($current === 'box' && $box === Mailbox::Unread) aria-current="page" @endif
            >
                <svg class="mr-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 10.5V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10" />
                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                    <circle cx="19" cy="17" r="3" />
                </svg>
                <span class="mr-nav-label">{{ Mailbox::Unread->label() }}</span>
                <span @class(['mr-badge', 'mr-badge-accent' => $boxCounts['unread'] > 0])>
                    {{ number_format($boxCounts['unread']) }}
                </span>
                @if ($boxCounts['unread'] > 0)
                    <span class="mr-nav-dot" aria-hidden="true"></span>
                @endif
            </a>
        @endif

        <a
            href="{{ $boxLink(Mailbox::SpamChecked) }}"
            class="mr-nav-link"
            title="{{ Mailbox::SpamChecked->label() }}"
            @if ($current === 'box' && $box === Mailbox::SpamChecked) aria-current="page" @endif
        >
            <svg class="mr-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
                <path d="m9 12 2 2 4-4" />
            </svg>
            <span class="mr-nav-label">{{ Mailbox::SpamChecked->label() }}</span>
            <span class="mr-badge">{{ number_format($boxCounts[Mailbox::SpamChecked->value]) }}</span>
        </a>
    </div>

    <div class="mr-side-more">
        @if ($people->isEmpty() && blank($peopleSearch))
            <h2 class="mr-side-sec">People</h2>
            <p class="mr-side-note">Every address that sends or receives mail shows up here.</p>
        @else
            {{--
                A native disclosure, so one block serves every width: a section
                that the script below opens on a wide screen, the rail's People
                button on a tablet, and a chip that opens a panel on a phone. A
                search submitted without JavaScript renders it open, so its
                results are on screen at any width.
            --}}
            <details class="mr-people-block" id="mr-people" @if (filled($peopleSearch)) open @endif>
                <summary class="mr-people-toggle" title="People">
                    <svg class="mr-people-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <span class="mr-people-heading">People</span>
                </summary>

                <div class="mr-people-panel">
                    <form
                        method="GET"
                        action="{{ route('mailroom.index') }}"
                        class="mr-find"
                        role="search"
                        data-mr-people-endpoint="{{ route('mailroom.people') }}"
                        data-mr-person="{{ $person }}"
                    >
                        @include('mailroom::partials.list-state-inputs', ['except' => ['page']])

                        <label class="mr-field">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="11" cy="11" r="7" />
                                <path d="m21 21-4.3-4.3" />
                            </svg>
                            <input
                                type="search"
                                name="people"
                                value="{{ $peopleSearch }}"
                                placeholder="Find a person"
                                class="mr-input mr-find-input"
                                aria-label="Find a person"
                                aria-controls="mr-people-results"
                                autocomplete="off"
                                spellcheck="false"
                            />
                            <span class="mr-spin" aria-hidden="true"></span>
                            <a
                                href="{{ route('mailroom.index', Arr::except($state, 'page')) }}"
                                class="mr-input-clear"
                                aria-label="Clear"
                                title="Clear (Esc)"
                            >
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
                            </a>
                        </label>
                    </form>

                    <div id="mr-people-results" aria-live="polite">
                        @include('mailroom::partials.people')
                    </div>
                </div>
            </details>

            {{-- Inline rather than pushed, so a wide screen paints the section
                 already open instead of opening it a moment later. --}}
            <script>
                (function () {
                    var block = document.getElementById('mr-people');

                    if (block && window.matchMedia('(min-width: 1200px)').matches) {
                        block.open = true;
                    }
                })();
            </script>
        @endif

        {{-- Usually absent. MAIL_MAILER=mailroom captures everything under one
             name, so a second mailer only appears when an application picks
             named mailers itself, which is exactly when filtering by them helps. --}}
        @if (count($mailerCounts) > 1)
            <h2 class="mr-side-sec">Mailers</h2>

            <div class="mr-nav">
                @foreach ($mailerCounts as $name => $total)
                    <a
                        href="{{ $link(['mailer' => $name]) }}"
                        class="mr-nav-link mr-nav-mailer"
                        title="{{ $name }}"
                        @if ($current === 'mailer' && $mailer === $name) aria-current="page" @endif
                    >
                        <svg class="mr-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2" y="2" width="20" height="8" rx="2" />
                            <rect x="2" y="14" width="20" height="8" rx="2" />
                            <path d="M6 6h.01M6 18h.01" />
                        </svg>
                        <span class="mr-nav-letter" aria-hidden="true">{{ $mailerMark($name) }}</span>
                        <span class="mr-nav-label">{{ $name }}</span>
                        <span class="mr-badge">{{ number_format($total) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mr-side-foot">
        @if ($messages->isNotEmpty())
            <div class="mr-hint">
                <kbd>j</kbd>
                <kbd>k</kbd>
                to move
                <span aria-hidden="true">&middot;</span>
                <kbd>/</kbd>
                to search
            </div>
        @endif

        <div class="mr-side-actions">
            <div class="mr-seg mr-theme" id="mr-theme" role="group" aria-label="Colour theme">
                <button
                    type="button"
                    data-mr-theme-choice="system"
                    aria-pressed="true"
                    title="Match system"
                    aria-label="Match system theme"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <path d="M8 21h8m-4-4v4" />
                    </svg>
                </button>
                <button
                    type="button"
                    data-mr-theme-choice="light"
                    aria-pressed="false"
                    title="Light"
                    aria-label="Light theme"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4" />
                        <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
                    </svg>
                </button>
                <button
                    type="button"
                    data-mr-theme-choice="dark"
                    aria-pressed="false"
                    title="Dark"
                    aria-label="Dark theme"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
                    </svg>
                </button>
            </div>

            @if ($boxCounts['inbox'] > 0)
                @include('mailroom::partials.clear-all', ['class' => 'mr-clear'])
            @endif
        </div>
    </div>
</nav>

{{-- Guarded like the block it drives, so an empty mailbox ships none of it. --}}
@if ($people->isNotEmpty() || filled($peopleSearch))
    @push('scripts')
        <script>
            (function () {
                var block = document.getElementById('mr-people');

                if (!block) {
                    return;
                }

                var form = block.querySelector('.mr-find');
                var input = form.querySelector('.mr-find-input');
                var field = input.closest('.mr-field');
                var clear = form.querySelector('.mr-input-clear');
                var results = document.getElementById('mr-people-results');
                var wide = window.matchMedia('(min-width: 1200px)');
                var timer = null;
                var pending = null;

                // Crossing the breakpoint turns the open section into a closed
                // button, and back.
                wide.addEventListener('change', function (event) {
                    block.open = event.matches;
                });

                function search() {
                    var url = new URL(form.getAttribute('data-mr-people-endpoint'), location.href);

                    url.searchParams.set('q', input.value.trim());
                    url.searchParams.set('person', form.getAttribute('data-mr-person'));

                    // A newer keystroke supersedes an answer still on its way.
                    if (pending) {
                        pending.abort();
                    }

                    var request = (pending = new AbortController());
                    field.classList.add('is-loading');

                    fetch(url, { headers: { Accept: 'text/html' }, signal: request.signal })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('People search failed: ' + response.status);
                            }

                            return response.text();
                        })
                        .then(function (html) {
                            // Our own endpoint's fragment, escaped server side.
                            results.innerHTML = html;
                        })
                        .catch(function (error) {
                            // Fall back to an ordinary search, which renders
                            // the same results server side.
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
                        event.stopPropagation();
                        reset();
                    }
                });

                // On a rail or a phone the block is a panel: focus its search
                // when it opens, and let Escape close it once the box is empty.
                block.addEventListener('toggle', function () {
                    if (block.open && !wide.matches) {
                        input.focus();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && block.open && !wide.matches) {
                        block.open = false;
                    }
                });
            })();
        </script>
    @endpush
@endif
