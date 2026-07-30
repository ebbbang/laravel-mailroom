@php
    use Ebbbang\TestMail\Support\PreviewKind;
    use Ebbbang\TestMail\Support\TextPreview;

    $kind = $file->previewKind();

    $src = $kind->servesBytes()
        ? route('test-mail.attachment.preview', ['message' => $message, 'attachment' => $file])
        : null;

    // Text-shaped kinds are read and escaped here rather than served, so a
    // .html or .js attachment is shown as source and can never execute.
    $rendered = $kind->isTextual() ? resolve(TextPreview::class)->render($file) : null;
@endphp

@switch (true)
    {{-- Images and SVG go through <img>, where an SVG cannot script or fetch. --}}
    @case ($kind === PreviewKind::Image || $kind === PreviewKind::Svg)
        <img class="tm-shot" src="{{ $src }}" alt="{{ $file->displayName() }}">
        @break

    {{--
        <object> rather than <iframe>: Safari has never rendered a PDF inside
        an iframe and needs <object>/<embed>, while Chrome and Firefox handle
        both. The type is pinned here and by the response, so the browser
        cannot be talked into treating this as anything but a PDF.
    --}}
    @case ($kind === PreviewKind::Pdf)
        <object class="tm-doc" type="application/pdf" data="{{ $src }}" title="{{ $file->displayName() }}">
            <div class="tm-blank">
                <div class="tm-empty-title">This browser will not display the PDF</div>
                <div>
                    <a href="{{ route('test-mail.attachment', ['message' => $message, 'attachment' => $file]) }}">
                        Download {{ $file->displayName() }}
                    </a>
                    to open it locally.
                </div>
            </div>
        </object>
        @break

    @case ($kind === PreviewKind::Audio)
        <audio controls preload="metadata" src="{{ $src }}"></audio>
        @break

    @case ($kind === PreviewKind::Video)
        <video controls preload="metadata" src="{{ $src }}"></video>
        @break

    @default
        <div class="tm-sheet">
            @if ($rendered['state'] === 'too-large' || $rendered['state'] === 'unreadable' || $rendered['state'] === 'empty')
                <div class="tm-blank">
                    <div class="tm-empty-title">
                        @switch ($rendered['state'])
                            @case ('too-large') Too large to preview @break
                            @case ('empty') This file is empty @break
                            @default Could not read this file
                        @endswitch
                    </div>
                    @foreach ($rendered['notes'] as $note)
                        <div>{{ $note }}</div>
                    @endforeach
                </div>
            @else
                @foreach ($rendered['notes'] as $note)
                    <div class="tm-sheet-note">{{ $note }}</div>
                @endforeach

                @if ($rendered['fields'])
                    <dl class="tm-sheet-fields">
                        @foreach ($rendered['fields'] as $label => $value)
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        @endforeach
                    </dl>
                @endif

                @if ($rendered['rows'])
                    @php $header = array_shift($rendered['rows']); @endphp
                    <table class="tm-grid">
                        <thead>
                            <tr>
                                <th class="tm-grid-num" scope="col">#</th>
                                @foreach ($header as $cell)
                                    <th scope="col">{{ $cell }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rendered['rows'] as $row)
                                <tr>
                                    <td class="tm-grid-num">{{ $loop->iteration }}</td>
                                    @foreach ($row as $cell)
                                        <td>{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif ($rendered['body'] !== null)
                    <pre class="tm-pre">{{ $rendered['body'] }}</pre>
                @endif
            @endif
        </div>
@endswitch
