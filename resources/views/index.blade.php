@extends('mailroom::layout')

@section('title', $selected ? $selected->displaySubject().' - Mailroom' : 'Mailroom')

@section('content')
    {{--
        .mr-reading is what a phone uses to show the message instead of the
        list. The server already knows which of the two this page is, so the
        switch needs no script; wider screens ignore the class.
    --}}
    <div @class(['mr-app', 'mr-reading' => $selected])>
        @include('mailroom::partials.sidebar')

        @include('mailroom::partials.list')

        <main class="mr-detail">
            @if ($selected)
                <div class="mr-back">
                    <a href="{{ route('mailroom.index', $state) }}">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m15 18-6-6 6-6" />
                        </svg>
                        {{ $listTitle }}
                    </a>
                </div>

                <article class="mr-card">
                    @include('mailroom::partials.detail', ['message' => $selected])
                </article>

                {{-- What a search shows in place of this message: searching
                     closes it, as Gmail does, rather than leaving its forms
                     and back link pointing at a list no longer on screen. --}}
                <template id="mr-reader-empty">
                    @include('mailroom::partials.reader-empty')
                </template>
            @else
                @include('mailroom::partials.reader-empty')
            @endif
        </main>
    </div>

    {{-- Delete and Clear all only exist once something has been captured. --}}
    @if ($boxCounts['inbox'] > 0)
        @include('mailroom::partials.confirm', ['total' => $boxCounts['inbox']])
    @endif

    @if (session('mailroom.status'))
        <div class="mr-flash" id="mr-flash" role="status" aria-live="polite" title="Dismiss">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 6 9 17l-5-5" />
            </svg>
            {{ session('mailroom.status') }}
        </div>

        @push('scripts')
            <script>
                (function () {
                    var flash = document.getElementById('mr-flash');

                    function dismiss() {
                        flash.hidden = true;
                    }

                    flash.addEventListener('click', dismiss);
                    setTimeout(dismiss, 5000);
                })();
            </script>
        @endpush
    @endif
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
                    fetch(url, { headers: { Accept: 'application/json' } })
                        .then(function (response) {
                            return response.ok ? response.json() : null;
                        })
                        .then(function (data) {
                            if (data && data.latest_id && data.latest_id !== known) {
                                banner.style.display = 'inline-flex';
                            }
                        })
                        .catch(function () {
                            /* a background poll is not worth a console error */
                        });
                }, {{ (int) $pollInterval * 1000 }});
            })();
        </script>
    @endif
@endpush
