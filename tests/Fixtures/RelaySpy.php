<?php

namespace Ebbbang\Mailroom\Tests\Fixtures;

use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * Stands in for a real relay so the tests can read exactly what would have
 * gone out, envelope included.
 *
 * Its own file rather than a second class beside a test: paratest gives each
 * test class its own process, so anything shared between two of them has to be
 * autoloadable or the second one cannot see it.
 */
class RelaySpy implements TransportInterface
{
    /** @var array<int, array{message: RawMessage, envelope: Envelope}> */
    public array $sent = [];

    protected ?string $failure = null;

    public function refuseWith(string $message): void
    {
        $this->failure = $message;
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($this->failure !== null) {
            throw new RuntimeException($this->failure);
        }

        $envelope ??= new Envelope(new Address('app@example.test'), [new Address('nobody@example.test')]);

        $this->sent[] = ['message' => $message, 'envelope' => $envelope];

        return new SentMessage($message, $envelope);
    }

    public function body(int $index): string
    {
        return $this->sent[$index]['message']->toString();
    }

    public function __toString(): string
    {
        return 'relay-spy';
    }
}
