@php
    $spamDriver = config('mailroom.spam.driver');
    $canSpamCheck = \Ebbbang\Mailroom\Mailroom::canSpamCheckFrom(request());

    // Worst offenders first: the rule that cost three points is the one worth
    // reading, and SpamAssassin returns them in no useful order.
    $spamRules = collect($message->spam_rules ?? [])->sortByDesc('score')->values();
@endphp

<div class="mr-spam">
    @if ($message->hasSpamResult())
        <div class="mr-spam-head">
            <div @class(['mr-spam-score', 'mr-spam-score-over' => $message->spam_score >= 5])>
                {{ number_format($message->spam_score, 1) }}
            </div>

            <div class="mr-spam-said">
                <strong>SpamAssassin scored this {{ number_format($message->spam_score, 1) }}.</strong>
                Its own default treats 5 and over as spam, which is a convention rather than a verdict from any
                particular provider. Checked through Postmark {{ $message->spam_checked_at->diffForHumans() }}.
            </div>

            @if ($canSpamCheck)
                <form method="POST" action="{{ route('mailroom.spam-check', $message) }}" class="mr-inline-form">
                    @csrf
                    <button type="submit" class="mr-btn">Check again</button>
                </form>
            @endif
        </div>

        @if ($spamRules->isNotEmpty())
            <table class="mr-table mr-spam-rules">
                <tbody>
                    @foreach ($spamRules as $rule)
                        <tr>
                            <td>{{ $rule['name'] ?: 'unnamed' }}</td>
                            <td>{{ $rule['description'] }}</td>
                            <td class="mr-spam-rule-score">{{ sprintf('%+.1f', $rule['score']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p class="mr-spam-note">
            Authentication and reputation are missing from this score, and cannot be otherwise: Mailroom captures before
            your relay signs and sends, so there is no SPF, DKIM or DMARC result, no Received chain, and no sending IP
            or domain to look up. What is scored is the content and the headers.
        </p>
    @elseif (blank($spamDriver))
        {{-- Rendered whether or not the feature is configured, the way the
             Forward button is: a line in the README teaches nobody. The route
             that would send anything is not registered until a driver is set. --}}
        <p class="mr-spam-note">
            <strong>Score this message with SpamAssassin.</strong>
            Switching this on adds a button that sends the message, attachments included, to Postmark's free SpamCheck
            API and shows the score and the rules that fired.
        </p>

        <pre class="mr-modal-pre"><code>MAILROOM_SPAM_DRIVER=postmark</code></pre>

        <p class="mr-spam-note">
            This is the one part of Mailroom that sends captured mail anywhere, so it stays off until you ask for it,
            and then sends nothing until somebody clicks the button on a message.
        </p>
    @elseif (! $canSpamCheck)
        <p class="mr-spam-note">
            <strong>Checking needs a signed-in user.</strong>
            Reading the mailbox and handing a message to a third party are different privileges outside local
            development. Set <code>MAILROOM_SPAM_REQUIRE_AUTH=false</code> to let anyone who can open the mailbox check
            messages.
        </p>
    @else
        <p class="mr-spam-note">
            <strong>Not checked yet.</strong>
            This sends the whole message, attachments included, to Postmark's SpamCheck API, which runs SpamAssassin and
            returns a score with the rules that fired. Nothing is sent until you click.
        </p>

        <form method="POST" action="{{ route('mailroom.spam-check', $message) }}" class="mr-inline-form">
            @csrf
            <button type="submit" class="mr-btn mr-btn-primary">Check with Postmark</button>
        </form>
    @endif
</div>
