@php
    use Ebbbang\Mailroom\Models\MailroomMessage as Msg;

    $files = $message->fileAttachments();
    $inline = $message->inlineAttachments();
    $hasHtml = filled($message->html_body);
    $hasText = filled($message->text_body);
    $firstPane = $hasHtml ? 'html' : ($hasText ? 'text' : 'headers');

    // Inline parts alone are enough to warrant the pane -- see the tab below.
    $hasAttachmentsPane = $files->isNotEmpty() || $inline->isNotEmpty();
@endphp

<div class="mr-detail-head">
    <h1 class="mr-subject">{{ $message->displaySubject() }}</h1>

    <div class="mr-fields">
        <span class="mr-field-label">From</span>
        <span class="mr-field-value">{{ Msg::formatAddressList($message->from) ?: '—' }}</span>

        <span class="mr-field-label">To</span>
        <span class="mr-field-value">{{ Msg::formatAddressList($message->to) ?: '—' }}</span>

        @if (filled($message->cc))
            <span class="mr-field-label">Cc</span>
            <span class="mr-field-value">{{ Msg::formatAddressList($message->cc) }}</span>
        @endif

        @if (filled($message->bcc))
            <span class="mr-field-label">Bcc</span>
            <span class="mr-field-value">{{ Msg::formatAddressList($message->bcc) }}</span>
        @endif

        @if (filled($message->reply_to))
            <span class="mr-field-label">Reply-To</span>
            <span class="mr-field-value">{{ Msg::formatAddressList($message->reply_to) }}</span>
        @endif

        <span class="mr-field-label">Sent</span>
        <span class="mr-field-value">
            {{ $message->sent_at?->toDayDateTimeString() ?? $message->created_at?->toDayDateTimeString() }}
            <span class="mr-badge" style="margin-left: 6px">{{ $message->mailer }}</span>
            <span class="mr-badge">{{ $message->humanSize() }}</span>
        </span>

        @if (filled($message->tags) || filled($message->metadata))
            <span class="mr-field-label">Tags</span>
            <span class="mr-field-value">
                @foreach ($message->tags ?? [] as $tag)
                    <span class="mr-badge mr-badge-accent">{{ $tag }}</span>
                @endforeach

                @foreach ($message->metadata ?? [] as $key => $value)
                    <span class="mr-badge">{{ $key }}: {{ $value }}</span>
                @endforeach
            </span>
        @endif
    </div>

    <div class="mr-actions">
        @if ($message->hasRaw())
            <a href="{{ route('mailroom.download', ['message' => $message, 'format' => 'eml']) }}" class="mr-btn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                </svg>
                .eml
            </a>
        @endif

        @if ($hasHtml)
            <a href="{{ route('mailroom.download', ['message' => $message, 'format' => 'html']) }}" class="mr-btn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                </svg>
                .html
            </a>
        @endif

        {{-- Always rendered. Nobody discovers a feature whose only trace is a
             line in the README, so the button is what teaches you forwarding
             exists; the dialog explains how to switch it on. The route itself
             is still absent until a mailer is configured, so this reveals the
             feature without opening a way to use it. --}}
        <button type="button" class="mr-btn" id="mr-forward-open" aria-haspopup="dialog">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m14 8 4 4-4 4M18 12H6" />
            </svg>
            Forward
        </button>

        <form
            method="POST"
            action="{{ route('mailroom.destroy', $message) }}"
            class="mr-inline-form"
            style="margin-left: auto"
            onsubmit="return confirm('Delete this message?');"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="mr-btn mr-btn-danger">Delete</button>
        </form>
    </div>
</div>

@php
    $forwardMailer = config('mailroom.forward.mailer');
    $canForward = \Ebbbang\Mailroom\Mailroom::canForwardFrom(request());
    $originalRecipient = $message->to[0]['address'] ?? '';
    $forwardError = session('mailroom.error') ?: $errors->first('to');
@endphp

{{-- Success is announced by the toast in the layout. A failure reopens this
     dialog instead, so the message sits beside the field you would change to
     put it right, and nothing on the page moves. --}}
<div
    class="mr-modal"
    id="mr-forward"
    role="dialog"
    aria-modal="true"
    aria-labelledby="mr-forward-title"
    @if ($forwardError) data-mr-open @endif
    hidden
>
    <div class="mr-modal-card" id="mr-forward-card">
        <div class="mr-modal-head">
            <h2 class="mr-modal-title" id="mr-forward-title">Forward this message</h2>
            <button
                type="button"
                class="mr-btn mr-btn-ghost"
                id="mr-forward-close"
                aria-label="Close"
                title="Close (Esc)"
            >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 6 6 18M6 6l12 12" />
                </svg>
            </button>
        </div>

        @if ($forwardError)
            <p class="mr-modal-error" role="alert">{{ $forwardError }}</p>
        @endif

        @if (! $message->hasRaw())
            <p class="mr-modal-body">
                Forwarding replays the stored copy of this message, and that copy is missing — so there is nothing to
                send. Everything shown above came from the database and is intact.
            </p>
        @elseif (blank($forwardMailer))
            <p class="mr-modal-body">
                Mailroom can send a copy of this message to a real inbox, so you can see how Gmail or Outlook renders
                it. Nothing is sent unless you ask for it here.
            </p>
            <p class="mr-modal-body">
                To switch it on, point <code>MAILROOM_FORWARD_MAILER</code> at a mailer from
                <code>config/mail.php</code> that can actually deliver:
            </p>
            <pre class="mr-modal-pre"><code>MAILROOM_FORWARD_MAILER=smtp</code></pre>
        @elseif (! $canForward)
            <p class="mr-modal-body">
                Forwarding is configured, but sending mail from your application needs a signed-in user outside local
                development. Being able to read the mailbox is not enough.
            </p>
            <p class="mr-modal-body">
                Sign in, or set <code>forward.require_authenticated_user</code> to <code>false</code>
                to let anyone who can open the mailbox send through it.
            </p>
        @else
            <form
                method="POST"
                action="{{ route('mailroom.forward', ['message' => $message, ...request()->only('search', 'mailer', 'page')]) }}"
                id="mr-forward-form"
            >
                @csrf
                <label class="mr-modal-label" for="mr-forward-to">Send a copy to</label>
                <input
                    type="email"
                    class="mr-modal-input"
                    name="to"
                    id="mr-forward-to"
                    value="{{ old('to', $originalRecipient) }}"
                    required
                    autocomplete="off"
                    spellcheck="false"
                />

                <p class="mr-modal-body">
                    Leaving the address as it is sends the message exactly as captured. Changing it rewrites the
                    <code>To</code> header and keeps the original in <code>X-Mailroom-Original-To</code>. Either way,
                    only this address receives a copy, and the message stored here is not altered.
                </p>

                <div class="mr-modal-foot">
                    <span class="mr-modal-meta">via <code>{{ $forwardMailer }}</code></span>
                    <button type="button" class="mr-btn" data-mr-forward-cancel>Cancel</button>
                    <button type="submit" class="mr-btn mr-btn-primary">Send</button>
                </div>
            </form>
        @endif
    </div>
</div>

@if ($message->hasMissingFiles())
    <div class="mr-notice">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
        </svg>
        <span>
            <strong>Stored files are missing.</strong>
            Everything above was read from the database and is intact, but the {{ $message->rawIsMissing() ? 'raw message' : 'attachment data' }} is
            no longer on the <code>{{ config('mailroom.storage.disk') }}</code> disk, so {{ $message->rawIsMissing() ? '.eml export' : 'downloads' }} cannot
            work. This is what an ephemeral or per-replica disk looks like — on Laravel Cloud and similar platforms,
            point <code>MAILROOM_DISK</code> at persistent object storage.
        </span>
    </div>
@endif

@if ($message->envelopeDivergesFromHeaders())
    <div class="mr-notice">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
        </svg>
        <span>
            <strong>Envelope differs from headers.</strong>
            This was delivered to <strong>{{ Msg::formatAddressList($message->envelope_recipients) }}</strong>, which is
            not what the message is addressed to. Something supplied a custom envelope, so the recipients above are not
            who actually received it.
        </span>
    </div>
@endif

<div class="mr-tabs" role="tablist">
    @if ($hasHtml)
        <button
            type="button"
            class="mr-tab"
            role="tab"
            aria-selected="{{ $firstPane === 'html' ? 'true' : 'false' }}"
            data-mr-tab="html"
        >
            HTML
        </button>
    @endif

    @if ($hasText)
        <button
            type="button"
            class="mr-tab"
            role="tab"
            aria-selected="{{ $firstPane === 'text' ? 'true' : 'false' }}"
            data-mr-tab="text"
        >
            Text
        </button>
    @endif

    {{--
        Inline parts count towards showing this tab even though they are not
        files. Without that, a message whose only attachments are embedded
        images has no tab at all, and the "plus N inline images" note inside
        the pane can never be reached.
    --}}
    @if ($hasAttachmentsPane)
        <button type="button" class="mr-tab" role="tab" aria-selected="false" data-mr-tab="files">
            Attachments
            @if ($files->isNotEmpty())
                <span class="mr-tab-count">{{ $files->count() }}</span>
            @endif
        </button>
    @endif

    <button
        type="button"
        class="mr-tab"
        role="tab"
        aria-selected="{{ $firstPane === 'headers' ? 'true' : 'false' }}"
        data-mr-tab="headers"
    >
        Headers
    </button>

    @if ($message->hasRaw())
        <button type="button" class="mr-tab" role="tab" aria-selected="false" data-mr-tab="raw">Raw</button>
    @endif
</div>

@if ($hasHtml)
    <div class="mr-panel" data-mr-pane="html">
        {{--
            The body is untrusted markup. It is loaded from a separate route
            into a sandbox carrying neither allow-scripts nor
            allow-same-origin, which leaves it in an opaque origin: no script
            execution, no reach into this page, no access to cookies.
        --}}
        <iframe
            class="mr-frame"
            sandbox
            referrerpolicy="no-referrer"
            title="Rendered message body"
            src="{{ route('mailroom.content', ['message' => $message, 'format' => 'html']) }}"
        ></iframe>
    </div>
@endif

@if ($hasText)
    <div class="mr-panel" data-mr-pane="text" @if ($firstPane !== 'text') hidden @endif>
        <pre class="mr-pre">{{ $message->text_body }}</pre>
    </div>
@endif

@if ($hasAttachmentsPane)
    <div class="mr-panel" data-mr-pane="files" hidden>
        @include('mailroom::partials.attachments', ['files' => $files, 'inline' => $inline, 'message' => $message])
    </div>
@endif

@if ($files->isNotEmpty())
    {{-- Outside the pane so the overlay is not clipped by its scroll context. --}}
    @include('mailroom::partials.preview')
@endif

<div class="mr-panel" data-mr-pane="headers" @if ($firstPane !== 'headers') hidden @endif>
    @include('mailroom::partials.headers', ['message' => $message])
</div>

@if ($message->hasRaw())
    <div class="mr-panel" data-mr-pane="raw" hidden>
        <iframe
            class="mr-frame"
            sandbox
            referrerpolicy="no-referrer"
            title="Raw message source"
            src="{{ route('mailroom.content', ['message' => $message, 'format' => 'raw']) }}"
        ></iframe>
    </div>
@endif

@push('scripts')
    <script>
        (function () {
            var tabs = document.querySelectorAll('[data-mr-tab]');
            var panes = document.querySelectorAll('[data-mr-pane]');

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    tabs.forEach(function (other) {
                        other.setAttribute('aria-selected', String(other === tab));
                    });

                    panes.forEach(function (pane) {
                        pane.hidden = pane.getAttribute('data-mr-pane') !== tab.getAttribute('data-mr-tab');
                    });
                });
            });
        })();
    </script>
@endpush

@push('scripts')
    <script>
        (function () {
            var open = document.getElementById('mr-forward-open');
            var modal = document.getElementById('mr-forward');
            var card = document.getElementById('mr-forward-card');

            if (!open || !modal) {
                return;
            }

            var field = document.getElementById('mr-forward-to');

            function show() {
                modal.hidden = false;

                if (field) {
                    field.focus();
                    field.select();
                } else {
                    document.getElementById('mr-forward-close').focus();
                }
            }

            function dismiss() {
                modal.hidden = true;
                open.focus();
            }

            open.addEventListener('click', show);
            document.getElementById('mr-forward-close').addEventListener('click', dismiss);

            // A failed send comes back as a fresh page load, so the dialog has
            // to reopen itself for the message inside it to be seen at all.
            if (modal.hasAttribute('data-mr-open')) {
                show();
            }

            modal.querySelectorAll('[data-mr-forward-cancel]').forEach(function (button) {
                button.addEventListener('click', dismiss);
            });

            // Backdrop dismissal, matching the attachment lightbox: the press
            // and the release both have to land outside the card, so dragging
            // a selection out of the field does not close the dialog.
            var pressedOutside = false;

            modal.addEventListener('mousedown', function (event) {
                pressedOutside = event.button === 0 && !card.contains(event.target);
            });

            modal.addEventListener('click', function (event) {
                if (pressedOutside && event.button === 0 && !card.contains(event.target)) {
                    dismiss();
                }

                pressedOutside = false;
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.hidden) {
                    dismiss();
                }
            });

            var form = document.getElementById('mr-forward-form');

            // The field is pre-filled with the real recipient, so a stray
            // Enter would email an actual customer. Naming the address in the
            // prompt makes that visible before it happens.
            if (form) {
                form.addEventListener('submit', function (event) {
                    if (!window.confirm('Forward this message to ' + field.value + '?')) {
                        event.preventDefault();
                    }
                });
            }
        })();
    </script>
@endpush
