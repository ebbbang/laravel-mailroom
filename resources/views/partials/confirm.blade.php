{{--
    One dialog for every destructive form on the page. A form opts in with
    data-mr-confirm naming a <template> that holds its wording, rendered by
    Blade where the form is, so a subject is escaped like any other text and
    the script only moves nodes. Without scripting the forms submit directly.

    Cancel takes focus when it opens, so a stray Enter backs out rather than
    deletes.
--}}
<div
    class="mr-modal"
    id="mr-confirm"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="mr-confirm-title"
    aria-describedby="mr-confirm-body"
    hidden
>
    <div class="mr-modal-card mr-confirm-card" id="mr-confirm-card">
        <span class="mr-confirm-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            </svg>
        </span>
        <h2 class="mr-modal-title" id="mr-confirm-title"></h2>
        <p class="mr-modal-body" id="mr-confirm-body"></p>
        <div class="mr-modal-foot">
            <button type="button" class="mr-btn" id="mr-confirm-cancel">Cancel</button>
            <button type="button" class="mr-btn mr-btn-destructive" id="mr-confirm-ok"></button>
        </div>
    </div>
</div>

<template id="mr-confirm-clear">
    <span data-mr-confirm-title>
        Delete all {{ number_format($total) }} {{ \Illuminate\Support\Str::plural('message', $total) }}?
    </span>
    <span data-mr-confirm-body>
        Every captured message and its attachments will be removed for good. This cannot be undone.
    </span>
    <span data-mr-confirm-action>Delete all</span>
</template>

<script>
    (function () {
        var dialog = document.getElementById('mr-confirm');
        var card = document.getElementById('mr-confirm-card');
        var title = document.getElementById('mr-confirm-title');
        var body = document.getElementById('mr-confirm-body');
        var cancel = document.getElementById('mr-confirm-cancel');
        var ok = document.getElementById('mr-confirm-ok');
        var pending = null;
        var returnTo = null;

        function part(template, name) {
            return template.content.querySelector('[data-mr-confirm-' + name + ']');
        }

        function open(form, trigger) {
            var template = document.getElementById(form.getAttribute('data-mr-confirm'));

            if (!template) {
                return false;
            }

            title.textContent = part(template, 'title').textContent.trim();
            body.replaceChildren.apply(body, Array.from(part(template, 'body').cloneNode(true).childNodes));
            ok.textContent = part(template, 'action').textContent.trim();

            pending = form;
            returnTo = trigger || document.activeElement;
            dialog.hidden = false;
            cancel.focus();

            return true;
        }

        function close() {
            dialog.hidden = true;
            pending = null;

            if (returnTo && returnTo.focus) {
                returnTo.focus();
            }
        }

        // Capture, so this runs before anything else listening for submit.
        document.addEventListener(
            'submit',
            function (event) {
                var form = event.target;

                if (form.hasAttribute('data-mr-confirm') && open(form, event.submitter)) {
                    event.preventDefault();
                }
            },
            true,
        );

        cancel.addEventListener('click', close);

        ok.addEventListener('click', function () {
            var form = pending;

            dialog.hidden = true;
            pending = null;

            // submit() skips the submit event, so this does not ask again.
            form.submit();
        });

        // The press and the release both have to land outside the card, as on
        // the forward dialog and the attachment lightbox.
        var pressedOutside = false;

        dialog.addEventListener('mousedown', function (event) {
            pressedOutside = event.button === 0 && !card.contains(event.target);
        });

        dialog.addEventListener('click', function (event) {
            if (pressedOutside && event.button === 0 && !card.contains(event.target)) {
                close();
            }

            pressedOutside = false;
        });

        document.addEventListener('keydown', function (event) {
            if (dialog.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                close();
            }

            // Two buttons, so keeping focus inside is a swap between them.
            if (event.key === 'Tab') {
                event.preventDefault();
                (document.activeElement === cancel ? ok : cancel).focus();
            }
        });
    })();
</script>
