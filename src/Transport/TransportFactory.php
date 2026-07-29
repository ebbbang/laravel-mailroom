<?php

namespace Ebbbang\TestMail\Transport;

use Ebbbang\TestMail\Exceptions\TestMailDisabledException;
use Ebbbang\TestMail\Recording\MessageRecorder;
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
    public function make(array $config): DatabaseTransport
    {
        /*
         * Fail here, at construction, rather than inside send(). The developer
         * finds out the moment the mailer is resolved and gets a stack trace
         * pointing at their configuration, instead of a message that silently
         * went nowhere.
         */
        if (! config('test-mail.enabled')) {
            throw TestMailDisabledException::forTransport();
        }

        return new DatabaseTransport(
            $this->container->make(MessageRecorder::class),
            $this->container->make(Dispatcher::class),
            $config['name'] ?? 'database',
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
        $forward = $config['forward'] ?? config('test-mail.forward');

        if (blank($forward)) {
            return null;
        }

        $name = $config['name'] ?? 'database';

        throw_if($forward === $name, InvalidArgumentException::class, sprintf('Mailer [%s] is configured to forward to itself. Point test-mail.forward at a different mailer.', $name));

        $forwardConfig = config('mail.mailers.'.$forward);

        throw_unless(is_array($forwardConfig), InvalidArgumentException::class, sprintf('Cannot forward captured mail to mailer [%s]: it is not defined in config/mail.php.', $forward));

        // Two database mailers pointing at each other would recurse forever.
        throw_if(($forwardConfig['transport'] ?? null) === 'database', InvalidArgumentException::class, sprintf('Mailer [%s] cannot forward to [%s], which is itself a database mailer.', $name, $forward));

        return $this->container->make(MailManager::class)->createSymfonyTransport(
            ['name' => $forward, ...$forwardConfig]
        );
    }
}
