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
            <span class="mr-badge" style="margin-left:6px">{{ $message->mailer }}</span>
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
                    <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                </svg>
                .eml
            </a>
        @endif

        @if ($hasHtml)
            <a href="{{ route('mailroom.download', ['message' => $message, 'format' => 'html']) }}" class="mr-btn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                </svg>
                .html
            </a>
        @endif

        <form
            method="POST"
            action="{{ route('mailroom.destroy', $message) }}"
            class="mr-inline-form"
            style="margin-left:auto"
            onsubmit="return confirm('Delete this message?')"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="mr-btn mr-btn-danger">Delete</button>
        </form>
    </div>
</div>

@if ($message->hasMissingFiles())
    <div class="mr-notice">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
        </svg>
        <span>
            <strong>Stored files are missing.</strong>
            Everything above was read from the database and is intact, but the
            {{ $message->rawIsMissing() ? 'raw message' : 'attachment data' }}
            is no longer on the <code>{{ config('mailroom.storage.disk') }}</code> disk, so
            {{ $message->rawIsMissing() ? '.eml export' : 'downloads' }} cannot work.
            This is what an ephemeral or per-replica disk looks like — on Laravel Cloud
            and similar platforms, point <code>MAILROOM_DISK</code> at persistent object storage.
        </span>
    </div>
@endif

@if ($message->envelopeDivergesFromHeaders())
    <div class="mr-notice">
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

<div class="mr-tabs" role="tablist">
    @if ($hasHtml)
        <button type="button" class="mr-tab" role="tab" aria-selected="{{ $firstPane === 'html' ? 'true' : 'false' }}" data-mr-tab="html">HTML</button>
    @endif

    @if ($hasText)
        <button type="button" class="mr-tab" role="tab" aria-selected="{{ $firstPane === 'text' ? 'true' : 'false' }}" data-mr-tab="text">Text</button>
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

    <button type="button" class="mr-tab" role="tab" aria-selected="{{ $firstPane === 'headers' ? 'true' : 'false' }}" data-mr-tab="headers">Headers</button>

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
