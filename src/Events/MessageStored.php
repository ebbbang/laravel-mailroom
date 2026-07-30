<?php

namespace Ebbbang\Mailroom\Events;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Symfony\Component\Mailer\SentMessage;

/**
 * Fired once a message has been captured. Laravel's own MessageSent event
 * carries no reference to the stored row, so this is how a listener (or a
 * test) gets hold of the record that was just written.
 */
class MessageStored
{
    public function __construct(
        public MailroomMessage $message,
        public SentMessage $sentMessage,
    ) {}
}
