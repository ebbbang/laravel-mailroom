<div
    class="tm-lightbox"
    id="tm-lightbox"
    role="dialog"
    aria-modal="true"
    aria-labelledby="tm-lightbox-title"
    hidden
>
    <div class="tm-lightbox-bar">
        <span class="tm-lightbox-title" id="tm-lightbox-title"></span>
        <span class="tm-lightbox-sub" id="tm-lightbox-meta"></span>

        <span class="tm-lightbox-tools">
            <span class="tm-lightbox-sub" id="tm-lightbox-count"></span>

            <button type="button" class="tm-lightbox-btn" id="tm-lightbox-prev" aria-label="Previous attachment" title="Previous (←)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
            </button>

            <button type="button" class="tm-lightbox-btn" id="tm-lightbox-next" aria-label="Next attachment" title="Next (→)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m9 18 6-6-6-6"/>
                </svg>
            </button>

            {{-- Points at the hardened download route, never the preview route. --}}
            <a class="tm-lightbox-btn" id="tm-lightbox-download" href="#" aria-label="Download this attachment" title="Download">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                </svg>
            </a>

            <button type="button" class="tm-lightbox-btn" id="tm-lightbox-close" aria-label="Close preview" title="Close (Esc)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </span>
    </div>

    <div class="tm-lightbox-body">
        <div class="tm-lightbox-stage" id="tm-lightbox-stage"></div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            var box = document.getElementById('tm-lightbox');

            if (!box) {
                return;
            }

            var stage = document.getElementById('tm-lightbox-stage');
            var title = document.getElementById('tm-lightbox-title');
            var meta = document.getElementById('tm-lightbox-meta');
            var count = document.getElementById('tm-lightbox-count');
            var download = document.getElementById('tm-lightbox-download');
            var prev = document.getElementById('tm-lightbox-prev');
            var next = document.getElementById('tm-lightbox-next');
            var close = document.getElementById('tm-lightbox-close');

            var triggers = Array.prototype.slice.call(document.querySelectorAll('[data-tm-preview]'));

            if (!triggers.length) {
                return;
            }

            var current = -1;
            var restoreFocusTo = null;

            function show(index) {
                var trigger = triggers[index];

                if (!trigger) {
                    return;
                }

                var template = document.querySelector('[data-tm-preview-content="' + index + '"]');

                current = index;

                // Replacing the stage contents drops the previous clone, which
                // is what stops a video or audio element carrying on playing
                // after you navigate away from it.
                stage.replaceChildren();

                if (template) {
                    stage.appendChild(template.content.cloneNode(true));
                }

                title.textContent = trigger.getAttribute('data-tm-name') || '';
                meta.textContent = trigger.getAttribute('data-tm-meta') || '';
                download.setAttribute('href', trigger.getAttribute('data-tm-download') || '#');

                count.textContent = triggers.length > 1
                    ? (index + 1) + ' of ' + triggers.length
                    : '';

                prev.disabled = index === 0;
                next.disabled = index === triggers.length - 1;

                // Keeps the open preview in the URL, so it survives a refresh
                // and can be handed to someone else.
                history.replaceState(null, '', '#preview-' + index);
            }

            function open(index) {
                restoreFocusTo = document.activeElement;
                box.hidden = false;
                document.body.style.overflow = 'hidden';
                show(index);
                close.focus();
            }

            function dismiss() {
                box.hidden = true;
                // Releases any media element still holding the file open.
                stage.replaceChildren();
                document.body.style.overflow = '';
                current = -1;

                if (location.hash.indexOf('#preview-') === 0) {
                    history.replaceState(null, '', location.pathname + location.search);
                }

                if (restoreFocusTo && typeof restoreFocusTo.focus === 'function') {
                    restoreFocusTo.focus();
                }
            }

            function step(delta) {
                var target = current + delta;

                if (target >= 0 && target < triggers.length) {
                    show(target);
                }
            }

            triggers.forEach(function (trigger, index) {
                trigger.addEventListener('click', function () {
                    open(index);
                });
            });

            prev.addEventListener('click', function () { step(-1); });
            next.addEventListener('click', function () { step(1); });
            close.addEventListener('click', dismiss);

            /*
             * Clicking anywhere that is not the preview itself closes it.
             *
             * "Not the preview" is decided by the target *being* one of the
             * chrome elements. Every kind puts a real element in the stage --
             * img.tm-shot, object.tm-doc, audio, video, div.tm-sheet -- so a
             * click on content always targets that element or a descendant and
             * never the chrome. New kinds therefore need no changes here.
             *
             * The bar is excluded on purpose: it holds the prev/next/download/
             * close controls, and closing on a stray click among them would be
             * annoying. PDFs need nothing special either, since <object> hosts
             * its own document and those clicks never reach this listener.
             */
            function isBackdrop(element) {
                return element instanceof Element && (
                    element.classList.contains('tm-lightbox') ||
                    element.classList.contains('tm-lightbox-body') ||
                    element.classList.contains('tm-lightbox-stage')
                );
            }

            /*
             * Press and release both have to land on the backdrop.
             *
             * Reacting to mousedown alone broke selecting text in the CSV and
             * text sheets: press inside the sheet, drag out, release on the
             * stage, and the resulting click targets the stage -- the nearest
             * common ancestor of the two -- so the preview would close and
             * throw the selection away.
             */
            var pressedOutside = false;

            box.addEventListener('mousedown', function (event) {
                pressedOutside = event.button === 0 && isBackdrop(event.target);
            });

            box.addEventListener('click', function (event) {
                if (pressedOutside && event.button === 0 && isBackdrop(event.target)) {
                    dismiss();
                }

                pressedOutside = false;
            });

            document.addEventListener('keydown', function (event) {
                if (box.hidden) {
                    return;
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    dismiss();
                    return;
                }

                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    step(-1);
                    return;
                }

                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    step(1);
                    return;
                }

                // Keep Tab inside the dialog while it is open.
                if (event.key === 'Tab') {
                    var focusable = box.querySelectorAll('button:not([disabled]), a[href], iframe, audio, video, [tabindex]:not([tabindex="-1"])');

                    if (!focusable.length) {
                        return;
                    }

                    var first = focusable[0];
                    var last = focusable[focusable.length - 1];

                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            });

            // #preview-2 opens the third attachment straight away, so a
            // preview survives a refresh and can be linked to.
            var deepLink = /^#preview-(\d+)$/.exec(location.hash);

            if (deepLink) {
                var wanted = parseInt(deepLink[1], 10);

                if (wanted >= 0 && wanted < triggers.length) {
                    open(wanted);
                }
            }
        })();
    </script>
@endpush
