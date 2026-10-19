<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex, nofollow" />
    <title>@yield('title', 'Mailroom')</title>

    {{--
        The sidebar's mark, so a Mailroom tab is told apart from the
        application's own. A data URI, because the package ships no assets;
        naming an icon also stops the browser asking for /favicon.ico, which
        an application may not have.
    --}}
    <link
        rel="icon"
        type="image/svg+xml"
        href="data:image/svg+xml,{{ rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#f53003"/><g transform="translate(1.6 1.6) scale(1.2)" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></g></svg>') }}"
    />

    {{--
        Applied before the stylesheet so the stored theme is in place on the
        very first paint. Anything later and the page flashes the system
        theme before switching.
    --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('mr-theme');
                if (stored === 'light' || stored === 'dark') {
                    document.documentElement.setAttribute('data-mr-theme', stored);
                }
            } catch (e) {
                /* private mode, storage disabled -- fall back to system */
            }
        })();
    </script>

    @include('mailroom::partials.styles')
</head>
<body class="mr-scope">
    @yield('content')

    <script>
        (function () {
            var group = document.getElementById('mr-theme');

            if (!group) {
                return;
            }

            var buttons = group.querySelectorAll('button[data-mr-theme-choice]');

            function paint(choice) {
                if (choice === 'system') {
                    document.documentElement.removeAttribute('data-mr-theme');
                } else {
                    document.documentElement.setAttribute('data-mr-theme', choice);
                }

                buttons.forEach(function (button) {
                    button.setAttribute('aria-pressed', String(button.getAttribute('data-mr-theme-choice') === choice));
                });
            }

            buttons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var choice = button.getAttribute('data-mr-theme-choice');

                    try {
                        choice === 'system'
                            ? localStorage.removeItem('mr-theme')
                            : localStorage.setItem('mr-theme', choice);
                    } catch (e) {
                        /* not fatal -- the choice still applies for this page */
                    }

                    paint(choice);
                });
            });

            var current = 'system';
            try {
                var stored = localStorage.getItem('mr-theme');
                if (stored === 'light' || stored === 'dark') {
                    current = stored;
                }
            } catch (e) {
                /* ignore */
            }

            paint(current);
        })();
    </script>

    @stack('scripts')
</body>
</html>
