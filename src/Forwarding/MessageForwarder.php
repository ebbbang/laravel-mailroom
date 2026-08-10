<?php

namespace Ebbbang\Mailroom\Forwarding;

use Ebbbang\Mailroom\Exceptions\CannotForwardException;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Illuminate\Mail\MailManager;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * Send one captured message on to a real inbox.
 *
 * The point of forwarding is to see what a mail client will do with the
 * message, so it replays the stored bytes rather than rebuilding an
 * approximation from the database. What Gmail renders is then what your
 * application actually produced, down to the MIME structure.
 */
class MessageForwarder
{
    public function __construct(
        protected MailManager $mailers,
        protected RawHeaderRewriter $rewriter,
    ) {}

    /**
     * @return bool whether the message was sent exactly as captured
     */
    public function forward(MailroomMessage $message, string $address): bool
    {
        $raw = $message->hasRaw() ? $message->raw() : null;

        throw_if($raw === null, CannotForwardException::nothingToSend());

        /*
         * Fidelity is worth most when the destination has not changed: sending
         * to one of the original recipients means the bytes can go out exactly
         * as captured, headers included. Redirecting elsewhere is a different
         * situation -- a message addressed to someone else, arriving in your
         * inbox, reads as a misdelivery -- so there the headers are retargeted.
         */
        $verbatim = $this->addressedTo($message, $address);

        $this->transport()->send(
            new RawMessage($verbatim ? $raw : $this->rewriter->redirect($raw, $address)),
            new Envelope(new Address($this->sender($message)), [new Address($address)])
        );

        return $verbatim;
    }

    /**
     * Was this message already going to that address?
     */
    protected function addressedTo(MailroomMessage $message, string $address): bool
    {
        $address = mb_strtolower(trim($address));

        foreach ($message->to ?? [] as $recipient) {
            if (mb_strtolower((string) ($recipient['address'] ?? '')) === $address) {
                return true;
            }
        }

        return false;
    }

    /**
     * The transport to hand the message to, with the guards that keep a
     * forward from quietly going nowhere.
     */
    protected function transport(): TransportInterface
    {
        $name = config('mailroom.forward.mailer');

        throw_if(blank($name), CannotForwardException::noMailerConfigured());

        $config = config('mail.mailers.'.$name);

        throw_unless(is_array($config), CannotForwardException::unknownMailer((string) $name));

        throw_if(
            ($config['transport'] ?? null) === 'mailroom',
            CannotForwardException::wouldCaptureAgain((string) $name)
        );

        return $this->mailers->mailer($name)->getSymfonyTransport();
    }

    /**
     * Who the forwarded copy comes from.
     *
     * The recorded envelope sender is preferred over the From header: it is
     * what the original delivery would have used as the return path, and it is
     * the address a relay is most likely to accept.
     */
    protected function sender(MailroomMessage $message): string
    {
        $sender = $message->envelope_sender ?: ($message->from[0]['address'] ?? null);

        throw_if(blank($sender), CannotForwardException::noSender());

        return (string) $sender;
    }
}
