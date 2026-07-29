@php
    use Ebbbang\TestMail\Models\TestMailMessage as Msg;

    $files = $message->fileAttachments();
    $inline = $message->inlineAttachments();
    $hasHtml = filled($message->html_body);
    $hasText = filled($message->text_body);
    $firstPane = $hasHtml ? 'html' : ($hasText ? 'text' : 'headers');
@endphp

<div class="tm-detail-head">
    <h1 class="tm-subject">{{ $message->displaySubject() }}</h1>

    <div class="tm-fields">
        <span class="tm-field-label">From</span>
        <span class="tm-field-value">{{ Msg::formatAddressList($message->from) ?: '—' }}</span>

        <span class="tm-field-label">To</span>
        <span class="tm-field-value">{{ Msg::formatAddressList($message->to) ?: '—' }}</span>

        @if (filled($message->cc))
            <span class="tm-field-label">Cc</span>
            <span class="tm-field-value">{{ Msg::formatAddressList($message->cc) }}</span>
        @endif

        @if (filled($message->bcc))
            <span class="tm-field-label">Bcc</span>
            <span class="tm-field-value">{{ Msg::formatAddressList($message->bcc) }}</span>
        @endif

        @if (filled($message->reply_to))
            <span class="tm-field-label">Reply-To</span>
            <span class="tm-field-value">{{ Msg::formatAddressList($message->reply_to) }}</span>
        @endif

        <span class="tm-field-label">Sent</span>
        <span class="tm-field-value">
            {{ $message->sent_at?->toDayDateTimeString() ?? $message->created_at?->toDayDateTimeString() }}
            <span class="tm-badge" style="margin-left:6px">{{ $message->mailer }}</span>
            <span class="tm-badge">{{ $message->humanSize() }}</span>
        </span>

        @if (filled($message->tags) || filled($message->metadata))
            <span class="tm-field-label">Tags</span>
            <span class="tm-field-value">
                @foreach ($message->tags ?? [] as $tag)
                    <span class="tm-badge tm-badge-accent">{{ $tag }}</span>
                @endforeach

                @foreach ($message->metadata ?? [] as $key => $value)
                    <span class="tm-badge">{{ $key }}: {{ $value }}</span>
                @endforeach
            </span>
        @endif
    </div>

    <div class="tm-actions">
        @if ($message->hasRaw())
            <a href="{{ route('test-mail.download', ['message' => $message, 'format' => 'eml']) }}" class="tm-btn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                </svg>
                .eml
            </a>
        @endif

        @if ($hasHtml)
            <a href="{{ route('test-mail.download', ['message' => $message, 'format' => 'html']) }}" class="tm-btn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                </svg>
                .html
            </a>
        @endif

        <form
            method="POST"
            action="{{ route('test-mail.destroy', $message) }}"
            class="tm-inline-form"
            style="margin-left:auto"
            onsubmit="return confirm('Delete this message?')"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="tm-btn tm-btn-danger">Delete</button>
        </form>
    </div>
</div>

@if ($message->hasMissingFiles())
    <div class="tm-notice">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
        </svg>
        <span>
            <strong>Stored files are missing.</strong>
            Everything above was read from the database and is intact, but the
            {{ $message->rawIsMissing() ? 'raw message' : 'attachment data' }}
            is no longer on the <code>{{ config('test-mail.storage.disk') }}</code> disk, so
            {{ $message->rawIsMissing() ? '.eml export' : 'downloads' }} cannot work.
            This is what an ephemeral or per-replica disk looks like — on Laravel Cloud
            and similar platforms, point <code>TEST_MAIL_DISK</code> at persistent object storage.
        </span>
    </div>
@endif

@if ($message->envelopeDivergesFromHeaders())
    <div class="tm-notice">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
        </svg>
        <span>
            <strong>Envelope differs from headers.</strong>
            This was delivered to <strong>{{ Msg::formatAddressList($message->envelope_recipients) }}</strong>,
            which is not what the message is addressed to. Something supplied a custom
            envelope, so the recipients above are not who actually received it.
        </span>
    </div>
@endif

<div class="tm-tabs" role="tablist">
    @if ($hasHtml)
        <button type="button" class="tm-tab" role="tab" aria-selected="{{ $firstPane === 'html' ? 'true' : 'false' }}" data-tm-tab="html">HTML</button>
    @endif

    @if ($hasText)
        <button type="button" class="tm-tab" role="tab" aria-selected="{{ $firstPane === 'text' ? 'true' : 'false' }}" data-tm-tab="text">Text</button>
    @endif

    @if ($files->isNotEmpty())
        <button type="button" class="tm-tab" role="tab" aria-selected="false" data-tm-tab="files">
            Attachments <span class="tm-tab-count">{{ $files->count() }}</span>
        </button>
    @endif

    <button type="button" class="tm-tab" role="tab" aria-selected="{{ $firstPane === 'headers' ? 'true' : 'false' }}" data-tm-tab="headers">Headers</button>

    @if ($message->hasRaw())
        <button type="button" class="tm-tab" role="tab" aria-selected="false" data-tm-tab="raw">Raw</button>
    @endif
</div>

@if ($hasHtml)
    <div class="tm-panel" data-tm-pane="html">
        {{--
            The body is untrusted markup. It is loaded from a separate route
            into a sandbox carrying neither allow-scripts nor
            allow-same-origin, which leaves it in an opaque origin: no script
            execution, no reach into this page, no access to cookies.
        --}}
        <iframe
            class="tm-frame"
            sandbox
            referrerpolicy="no-referrer"
            title="Rendered message body"
            src="{{ route('test-mail.content', ['message' => $message, 'format' => 'html']) }}"
        ></iframe>
    </div>
@endif

@if ($hasText)
    <div class="tm-panel" data-tm-pane="text" @if ($firstPane !== 'text') hidden @endif>
        <pre class="tm-pre">{{ $message->text_body }}</pre>
    </div>
@endif

@if ($files->isNotEmpty())
    <div class="tm-panel" data-tm-pane="files" hidden>
        @include('test-mail::partials.attachments', ['files' => $files, 'inline' => $inline, 'message' => $message])
    </div>
@endif

<div class="tm-panel" data-tm-pane="headers" @if ($firstPane !== 'headers') hidden @endif>
    @include('test-mail::partials.headers', ['message' => $message])
</div>

@if ($message->hasRaw())
    <div class="tm-panel" data-tm-pane="raw" hidden>
        <iframe
            class="tm-frame"
            sandbox
            referrerpolicy="no-referrer"
            title="Raw message source"
            src="{{ route('test-mail.content', ['message' => $message, 'format' => 'raw']) }}"
        ></iframe>
    </div>
@endif

@push('scripts')
    <script>
        (function () {
            var tabs = document.querySelectorAll('[data-tm-tab]');
            var panes = document.querySelectorAll('[data-tm-pane]');

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    tabs.forEach(function (other) {
                        other.setAttribute('aria-selected', String(other === tab));
                    });

                    panes.forEach(function (pane) {
                        pane.hidden = pane.getAttribute('data-tm-pane') !== tab.getAttribute('data-tm-tab');
                    });
                });
            });
        })();
    </script>
@endpush
