@php
    use Ebbbang\TestMail\Support\PreviewKind;

    // Only the previewable rows take part in lightbox navigation, so they need
    // their own contiguous index -- a non-previewable row in the middle must
    // not leave a hole in the prev/next sequence.
    $previewable = $files->filter->isPreviewable()->values();
@endphp

<div class="tm-files">
    @foreach ($files as $file)
        @php $position = $previewable->search(fn ($candidate) => $candidate->is($file)); @endphp

        @if ($file->isPreviewable())
            <button
                type="button"
                class="tm-file"
                data-tm-preview="{{ $position }}"
                data-tm-name="{{ $file->displayName() }}"
                data-tm-meta="{{ $file->previewKind()->label() }} · {{ $file->humanSize() }}"
                data-tm-download="{{ route('test-mail.attachment', ['message' => $message, 'attachment' => $file]) }}"
            >
                @if ($file->previewKind() === PreviewKind::Image || $file->previewKind() === PreviewKind::Svg)
                    <img
                        class="tm-thumb"
                        loading="lazy"
                        alt=""
                        src="{{ route('test-mail.attachment.preview', ['message' => $message, 'attachment' => $file]) }}"
                    >
                @else
                    <span class="tm-file-icon">
                        @include('test-mail::partials.file-icon', ['kind' => $file->previewKind()])
                    </span>
                @endif

                <span class="tm-file-name">{{ $file->displayName() }}</span>

                <span class="tm-file-actions">
                    <span class="tm-file-meta">{{ $file->mime_type }} · {{ $file->humanSize() }}</span>
                </span>
            </button>
        @else
            <div class="tm-file">
                <span class="tm-file-icon">
                    @include('test-mail::partials.file-icon', ['kind' => PreviewKind::None])
                </span>

                <span class="tm-file-name">{{ $file->displayName() }}</span>

                <span class="tm-file-actions">
                    @if ($file->wasSkipped())
                        <span class="tm-noprev">not stored, over storage.max_attachment_size</span>
                    @elseif ($file->isMissing())
                        <span class="tm-noprev">file missing from the {{ config('test-mail.storage.disk') }} disk</span>
                    @else
                        <span class="tm-file-meta">{{ $file->humanSize() }}</span>
                        <span class="tm-noprev">no preview for {{ $file->extensionLabel() }}</span>
                    @endif

                    @if ($file->hasContents())
                        <a
                            class="tm-download"
                            title="Download {{ $file->displayName() }}"
                            aria-label="Download {{ $file->displayName() }}"
                            href="{{ route('test-mail.attachment', ['message' => $message, 'attachment' => $file]) }}"
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                            </svg>
                        </a>
                    @endif
                </span>
            </div>
        @endif
    @endforeach

    @if ($inline->isNotEmpty())
        <p class="tm-file-meta" style="margin:{{ $files->isEmpty() ? '0' : '6px 0 0' }}">
            {{ $files->isEmpty() ? '' : 'Plus ' }}{{ $inline->count() }}
            inline {{ Str::plural('image', $inline->count()) }}
            embedded in the HTML body{{ $files->isEmpty() ? '; no files are attached' : '' }}.
        </p>
    @endif
</div>

{{--
    Preview content lives in <template> elements and is cloned into the
    lightbox on demand. Nothing inside a template is fetched or parsed until
    it is cloned, so opening the message costs no image, media or PDF
    requests -- only the row thumbnails above load up front.
--}}
@foreach ($previewable as $index => $file)
    <template data-tm-preview-content="{{ $index }}">
        @include('test-mail::partials.preview-content', ['message' => $message, 'file' => $file])
    </template>
@endforeach
