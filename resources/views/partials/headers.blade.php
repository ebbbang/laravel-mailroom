@php
    use Ebbbang\Mailroom\Support\AddressRole;

    $bcc = $message->addressesFor(AddressRole::Bcc);
    $envelopeTo = $message->addressesFor(AddressRole::EnvelopeTo);
@endphp

<table class="mr-table">
    <tbody>
        @forelse ($message->headers ?? [] as $name => $value)
            <tr>
                <td>{{ $name }}</td>
                <td>{{ $value }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2"><span class="mr-note">No headers were recorded for this message.</span></td>
            </tr>
        @endforelse

        @if ($bcc->isNotEmpty())
            <tr>
                <td>Bcc</td>
                <td>
                    {{ $bcc->display() }}
                    <div class="mr-note">
                        Recorded separately, because a Bcc header is stripped before a message is sent, so it will not
                        appear in the .eml export.
                    </div>
                </td>
            </tr>
        @endif

        @if ($message->envelopeSender())
            <tr>
                <td>Envelope-Sender</td>
                <td>{{ $message->envelopeSender()->address }}</td>
            </tr>
        @endif

        @if ($envelopeTo->isNotEmpty())
            <tr>
                <td>Envelope-To</td>
                <td>{{ $envelopeTo->display() }}</td>
            </tr>
        @endif
    </tbody>
</table>
