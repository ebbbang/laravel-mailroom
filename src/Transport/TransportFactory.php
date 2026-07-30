<?php

namespace Ebbbang\Mailroom\Transport;

use Ebbbang\Mailroom\Exceptions\MailroomDisabledException;
use Ebbbang\Mailroom\Recording\MessageRecorder;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Mail\MailManager;
use InvalidArgumentException;
use Symfony\Component\Mailer\Transport\TransportInterface;

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
            $this->forwardTransport($config),
        );
    }

    /**
     * Build the transport that captured mail should be handed on to, if any.
     *
     * @param  array<string, mixed>  $config
     */
    protected function forwardTransport(array $config): ?TransportInterface
    {
        $forward = $config['forward'] ?? config('mailroom.forward');

        if (blank($forward)) {
            return null;
        }

        $name = $config['name'] ?? 'mailroom';

        throw_if($forward === $name, InvalidArgumentException::class, sprintf('Mailer [%s] is configured to forward to itself. Point mailroom.forward at a different mailer.', $name));

        $forwardConfig = config('mail.mailers.'.$forward);

        throw_unless(is_array($forwardConfig), InvalidArgumentException::class, sprintf('Cannot forward captured mail to mailer [%s]: it is not defined in config/mail.php.', $forward));

        // Two database mailers pointing at each other would recurse forever.
        throw_if(($forwardConfig['transport'] ?? null) === 'mailroom', InvalidArgumentException::class, sprintf('Mailer [%s] cannot forward to [%s], which is itself a mailroom mailer.', $name, $forward));

        return $this->container->make(MailManager::class)->createSymfonyTransport(
            ['name' => $forward, ...$forwardConfig]
        );
    }
}
