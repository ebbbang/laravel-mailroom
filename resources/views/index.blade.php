@extends('test-mail::layout')

@section('title', $selected ? $selected->displaySubject().' — Test Mail' : 'Test Mail')

@section('content')
    <header class="tm-header">
        <a href="{{ route('test-mail.index') }}" class="tm-brand">
            <span class="tm-mark">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                </svg>
            </span>
            Test Mail
        </a>

        <span class="tm-count">
            {{ number_format($messages->total()) }} {{ Str::plural('message', $messages->total()) }}
        </span>

        <button type="button" class="tm-poll" id="tm-poll">
            <span class="tm-dot"></span>
            New mail
        </button>

        <div class="tm-header-actions">
            <form method="GET" action="{{ route('test-mail.index') }}" class="tm-filters">
                <label class="tm-field">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
                    </svg>
                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search subject, body, recipients"
                        class="tm-input"
                        aria-label="Search messages"
                    >
                </label>

                @if (count($mailers) > 1)
                    <select name="mailer" class="tm-select" aria-label="Filter by mailer">
                        <option value="">All mailers</option>
                        @foreach ($mailers as $name)
                            <option value="{{ $name }}" @selected($mailer === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                @endif

                <button type="submit" class="tm-btn">Filter</button>

                @if (filled($search) || filled($mailer))
                    <a href="{{ route('test-mail.index') }}" class="tm-btn tm-btn-ghost">Reset</a>
                @endif
            </form>

            <div class="tm-theme" id="tm-theme" role="group" aria-label="Colour theme">
                <button type="button" data-tm-theme-choice="system" aria-pressed="true" title="Match system" aria-label="Match system theme">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8m-4-4v4"/>
                    </svg>
                </button>
                <button type="button" data-tm-theme-choice="light" aria-pressed="false" title="Light" aria-label="Light theme">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                    </svg>
                </button>
                <button type="button" data-tm-theme-choice="dark" aria-pressed="false" title="Dark" aria-label="Dark theme">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                    </svg>
                </button>
            </div>

            @if ($messages->total() > 0)
                <form
                    method="POST"
                    action="{{ route('test-mail.clear') }}"
                    class="tm-inline-form"
                    onsubmit="return confirm('Delete every captured message? This cannot be undone.')"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="tm-btn tm-btn-danger">Clear all</button>
                </form>
            @endif
        </div>
    </header>

    @if (session('test-mail.status'))
        <div class="tm-flash">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 6 9 17l-5-5"/>
            </svg>
            {{ session('test-mail.status') }}
        </div>
    @endif

    <div class="tm-main">
        @include('test-mail::partials.list')

        <div class="tm-detail">
            @if ($selected)
                @include('test-mail::partials.detail', ['message' => $selected])
            @else
                <div class="tm-empty">
                    <span class="tm-empty-mark">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        </svg>
                    </span>

                    @if ($messages->total() > 0)
                        <div class="tm-empty-title">Select a message</div>
                        <div>Pick anything from the list to read it.</div>
                    @else
                        <div class="tm-empty-title">No mail captured yet</div>
                        <div>
                            Set <code class="tm-code">MAIL_MAILER=database</code> and send something.<br>
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
                var url = @json(route('test-mail.recent'));
                var known = @json($messages->total() > 0 ? $messages->first()->id : null);
                var banner = document.getElementById('tm-poll');

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
