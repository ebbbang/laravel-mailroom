<table class="tm-table">
    <tbody>
        @forelse ($message->headers ?? [] as $name => $value)
            <tr>
                <td>{{ $name }}</td>
                <td>{{ $value }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" style="font-family:inherit;color:var(--tm-text-faint)">
                    No headers were recorded for this message.
                </td>
            </tr>
        @endforelse

        @if (filled($message->bcc))
            <tr>
                <td>Bcc</td>
                <td>
                    {{ \Ebbbang\TestMail\Models\TestMailMessage::formatAddressList($message->bcc) }}
                    <div style="font-family:inherit;color:var(--tm-text-faint);margin-top:3px">
                        Recorded separately — a Bcc header is stripped before a message is sent,
                        so it will not appear in the .eml export.
                    </div>
                </td>
            </tr>
        @endif

        @if ($message->envelope_sender)
            <tr>
                <td>Envelope-Sender</td>
                <td>{{ $message->envelope_sender }}</td>
            </tr>
        @endif

        @if (filled($message->envelope_recipients))
            <tr>
                <td>Envelope-To</td>
                <td>{{ \Ebbbang\TestMail\Models\TestMailMessage::formatAddressList($message->envelope_recipients) }}</td>
            </tr>
        @endif
    </tbody>
</table>
