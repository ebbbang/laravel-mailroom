@extends('mailroom::layout')

@section('title', $selected ? $selected->displaySubject().' — Mailroom' : 'Mailroom')

@section('content')
    <header class="mr-header">
        <a href="{{ route('mailroom.index') }}" class="mr-brand">
            <span class="mr-mark">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                </svg>
            </span>
            Mailroom
        </a>

        <span class="mr-count">
            {{ number_format($messages->total()) }} {{ Str::plural('message', $messages->total()) }}
        </span>

        <button type="button" class="mr-poll" id="mr-poll">
            <span class="mr-dot"></span>
            New mail
        </button>

        <div class="mr-header-actions">
            <form method="GET" action="{{ route('mailroom.index') }}" class="mr-filters">
                <label class="mr-field">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
                    </svg>
                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search subject, body, recipients"
                        class="mr-input"
                        aria-label="Search messages"
                    >
                </label>

                @if (count($mailers) > 1)
                    <select name="mailer" class="mr-select" aria-label="Filter by mailer">
                        <option value="">All mailers</option>
                        @foreach ($mailers as $name)
                            <option value="{{ $name }}" @selected($mailer === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                @endif

                <button type="submit" class="mr-btn">Filter</button>

                @if (filled($search) || filled($mailer))
                    <a href="{{ route('mailroom.index') }}" class="mr-btn mr-btn-ghost">Reset</a>
                @endif
            </form>

            <div class="mr-theme" id="mr-theme" role="group" aria-label="Colour theme">
                <button type="button" data-mr-theme-choice="system" aria-pressed="true" title="Match system" aria-label="Match system theme">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8m-4-4v4"/>
                    </svg>
                </button>
                <button type="button" data-mr-theme-choice="light" aria-pressed="false" title="Light" aria-label="Light theme">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                    </svg>
                </button>
                <button type="button" data-mr-theme-choice="dark" aria-pressed="false" title="Dark" aria-label="Dark theme">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                    </svg>
                </button>
            </div>

            @if ($messages->total() > 0)
                <form
                    method="POST"
                    action="{{ route('mailroom.clear') }}"
                    class="mr-inline-form"
                    onsubmit="return confirm('Delete every captured message? This cannot be undone.')"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="mr-btn mr-btn-danger">Clear all</button>
                </form>
            @endif
        </div>
    </header>

    @if (session('mailroom.status'))
        <div class="mr-flash">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 6 9 17l-5-5"/>
            </svg>
            {{ session('mailroom.status') }}
        </div>
    @endif

    <div class="mr-main">
        @include('mailroom::partials.list')

        <div class="mr-detail">
            @if ($selected)
                @include('mailroom::partials.detail', ['message' => $selected])
            @else
                <div class="mr-empty">
                    <span class="mr-empty-mark">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        </svg>
                    </span>

                    @if ($messages->total() > 0)
                        <div class="mr-empty-title">Select a message</div>
                        <div>Pick anything from the list to read it.</div>
                    @else
                        <div class="mr-empty-title">No mail captured yet</div>
                        <div>
                            Set <code class="mr-code">MAIL_MAILER=mailroom</code> and send something.<br>
                            Everything your app mails will land here.
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @if ($pollInterval)
        <script>
            (function () {
                var url = @json(route('mailroom.recent'));
                var known = @json($latestId);
                var banner = document.getElementById('mr-poll');

                if (!banner) {
                    return;
                }

                banner.addEventListener('click', function () {
                    window.location.reload();
                });

                setInterval(function () {
                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(function (response) { return response.ok ? response.json() : null; })
                        .then(function (data) {
                            if (data && data.latest_id && data.latest_id !== known) {
                                banner.style.display = 'inline-flex';
                            }
                        })
                        .catch(function () { /* a background poll is not worth a console error */ });
                }, {{ (int) $pollInterval * 1000 }});
            })();
        </script>
    @endif
@endpush
