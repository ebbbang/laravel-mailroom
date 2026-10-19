<div class="mr-card">
    <div class="mr-empty">
        <span class="mr-empty-mark">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="4" width="20" height="16" rx="2" />
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
            </svg>
        </span>

        @if ($boxCounts['inbox'] > 0)
            <div class="mr-empty-title">Select a message</div>
            <div>Pick anything from the list to read it, or press <kbd>j</kbd> to open the first one.</div>
        @else
            <div class="mr-empty-title">No mail captured yet</div>
            <div>
                Set <code class="mr-code">MAIL_MAILER=mailroom</code> and send something.<br />
                Everything your app mails will land here.
            </div>
        @endif
    </div>
</div>
