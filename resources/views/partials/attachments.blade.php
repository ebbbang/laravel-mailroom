<div class="tm-files">
    @foreach ($files as $file)
        @if ($file->hasContents())
            <a href="{{ route('test-mail.attachment', ['message' => $message, 'attachment' => $file]) }}" class="tm-file">
                <span class="tm-file-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
                        <path d="M14 2v6h6"/>
                    </svg>
                </span>
                <span class="tm-file-name">{{ $file->displayName() }}</span>
                <span class="tm-file-meta">{{ $file->mime_type }} · {{ $file->humanSize() }}</span>
            </a>
        @else
            <div class="tm-file" style="opacity:.6">
                <span class="tm-file-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
                        <path d="M14 2v6h6"/>
                    </svg>
                </span>
                <span class="tm-file-name">{{ $file->displayName() }}</span>
                <span class="tm-file-meta">
                    {{ $file->humanSize() }} ·
                    {{ $file->wasSkipped()
                        ? 'not stored, over storage.max_attachment_size'
                        : 'file missing from the '.config('test-mail.storage.disk').' disk' }}
                </span>
            </div>
        @endif
    @endforeach

    @if ($inline->isNotEmpty())
        <p class="tm-file-meta" style="margin:6px 0 0">
            Plus {{ $inline->count() }} inline {{ Str::plural('image', $inline->count()) }}
            embedded in the HTML body.
        </p>
    @endif
</div>
