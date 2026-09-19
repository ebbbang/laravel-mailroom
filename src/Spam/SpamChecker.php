<?php

namespace Ebbbang\Mailroom\Spam;

use Ebbbang\Mailroom\Exceptions\CannotCheckSpamException;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Score one captured message with SpamAssassin, by asking someone who runs it.
 *
 * Mailroom sees a message before the relay does, which puts half of what a real
 * filter judges out of reach: SPF, DKIM and DMARC have not been applied, there
 * is no Received chain, and there is no sending IP or domain to look up. What
 * is left is the content and the headers, which is exactly what SpamAssassin's
 * static rules read.
 *
 * So this sends the stored bytes and reports what comes back. Writing our own
 * rule set instead would mean inventing weights that SpamAssassin derives from
 * a trained corpus, and the number would look like theirs while meaning far
 * less.
 */
class SpamChecker
{
    public function check(MailroomMessage $message): SpamResult
    {
        $driver = (string) config('mailroom.spam.driver');

        throw_if(blank($driver), CannotCheckSpamException::noDriverConfigured());
        throw_if($driver !== 'postmark', CannotCheckSpamException::unknownDriver($driver));

        $raw = $message->hasRaw() ? $message->raw() : null;

        throw_if($raw === null, CannotCheckSpamException::nothingToCheck());

        return SpamResult::fromPostmark($this->ask($raw));
    }

    /**
     * @return array<string, mixed>
     */
    protected function ask(string $raw): array
    {
        $endpoint = (string) config('mailroom.spam.endpoint');

        try {
            /*
             * "long" asks for the rules alongside the score. The score alone
             * would leave a reader with a number and nothing to act on, and the
             * rules are the part that says which line of the message to fix.
             */
            $response = Http::asJson()
                ->timeout((int) config('mailroom.spam.timeout', 15))
                ->post($endpoint, ['email' => $raw, 'options' => 'long']);
        } catch (ConnectionException $connectionException) {
            throw CannotCheckSpamException::unreachable($endpoint, $connectionException->getMessage());
        }

        $payload = (array) $response->json();

        // The service answers 200 with success:false for a message it will not
        // score, so the status alone is not enough to go on.
        throw_if(
            $response->failed() || ($payload['success'] ?? false) !== true,
            CannotCheckSpamException::refused((string) ($payload['message'] ?? 'HTTP '.$response->status()))
        );

        return $payload;
    }
}
