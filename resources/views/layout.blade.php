<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Test Mail')</title>

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
            } catch (e) { /* private mode, storage disabled -- fall back to system */ }
        })();
    </script>

    @include('mailroom::partials.styles')
</head>
<body class="mr-scope">
    <div class="mr-shell">
        @yield('content')
    </div>

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
                    button.setAttribute(
                        'aria-pressed',
                        String(button.getAttribute('data-mr-theme-choice') === choice)
                    );
                });
            }

            buttons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var choice = button.getAttribute('data-mr-theme-choice');

                    try {
                        choice === 'system'
                            ? localStorage.removeItem('mr-theme')
                            : localStorage.setItem('mr-theme', choice);
                    } catch (e) { /* not fatal -- the choice still applies for this page */ }

                    paint(choice);
                });
            });

            var current = 'system';
            try {
                var stored = localStorage.getItem('mr-theme');
                if (stored === 'light' || stored === 'dark') {
                    current = stored;
                }
            } catch (e) { /* ignore */ }

            paint(current);
        })();
    </script>

    @stack('scripts')
</body>
</html>
