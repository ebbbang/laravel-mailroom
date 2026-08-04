<?php

namespace Ebbbang\Mailroom\Transport;

use Ebbbang\Mailroom\Exceptions\MailroomDisabledException;
use Ebbbang\Mailroom\Recording\MessageRecorder;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

class TransportFactory
{
    public function __construct(protected Container $container) {}

    /**
     * @param  array<string, mixed>  $config  The mailer's config, including the
     *                                        'name' key MailManager injects.
     */
    public function make(array $config): MailroomTransport
    {
        /*
         * Fail here, at construction, rather than inside send(). The developer
         * finds out the moment the mailer is resolved and gets a stack trace
         * pointing at their configuration, instead of a message that silently
         * went nowhere.
         */
        if (! config('mailroom.enabled')) {
            throw MailroomDisabledException::forTransport();
        }

        return new MailroomTransport(
            $this->container->make(MessageRecorder::class),
            $this->container->make(Dispatcher::class),
            $config['name'] ?? 'mailroom',
        );
    }
}
