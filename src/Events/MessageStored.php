<?php

namespace Ebbbang\TestMail\Events;

use Ebbbang\TestMail\Models\TestMailMessage;
use Symfony\Component\Mailer\SentMessage;

/**
 * Fired once a message has been captured. Laravel's own MessageSent event
 * carries no reference to the stored row, so this is how a listener (or a
 * test) gets hold of the record that was just written.
 */
class MessageStored
{
    public function __construct(
        public TestMailMessage $message,
        public SentMessage $sentMessage,
    ) {}
}
