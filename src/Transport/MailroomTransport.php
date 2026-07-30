<?php

namespace Ebbbang\Mailroom\Transport;

use Ebbbang\Mailroom\Events\MessageStored;
use Ebbbang\Mailroom\Recording\MessageRecorder;
use Illuminate\Contracts\Events\Dispatcher;
use Stringable;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Captures every outgoing message to the database.
 *
 * Modelled on Illuminate\Mail\Transport\ArrayTransport: a terminal transport
 * implementing TransportInterface directly rather than extending Symfony's
 * AbstractTransport, since there is no remote endpoint to talk to.
 */
class MailroomTransport implements Stringable, TransportInterface
{
    public function __construct(
        protected MessageRecorder $recorder,
        protected Dispatcher $events,
        protected string $mailerName = 'mailroom',
        protected ?TransportInterface $next = null,
    ) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $envelope ??= Envelope::create($message);

        $stored = $this->recorder->record($message, $envelope, $this->mailerName);

        // Terminal by default. When mailroom.forward names another mailer we
        // hand the message on afterwards, so staging can capture and still
        // deliver in one pass.
        $sent = $this->next?->send($message, $envelope)
            ?? new SentMessage($message, $envelope);

        $this->events->dispatch(new MessageStored($stored, $sent));

        return $sent;
    }

    public function forwardsTo(): ?TransportInterface
    {
        return $this->next;
    }

    public function __toString(): string
    {
        return 'mailroom';
    }
}
