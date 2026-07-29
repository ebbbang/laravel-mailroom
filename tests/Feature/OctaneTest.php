<?php

namespace Ebbbang\TestMail\Tests\Feature;

use Ebbbang\TestMail\Events\MessageStored;
use Ebbbang\TestMail\Models\TestMailMessage;
use Ebbbang\TestMail\TestMail;
use Ebbbang\TestMail\Tests\Fixtures\OrderShipped;
use Ebbbang\TestMail\Tests\TestCase;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

/**
 * Octane keeps one base application and hands each request a sandbox clone.
 * Services resolved during a request live and die in that sandbox; only the
 * warmed ones are shared. "mail.manager" is not warmed, so it is rebuilt per
 * request -- and the transport has to be built from the same container the
 * mail manager came from, not from the container the provider booted with.
 *
 * These tests model that by cloning the application the way Octane's
 * ApplicationGateway does, rather than by booting a real Octane worker.
 */
class OctaneTest extends TestCase
{
    protected function tearDown(): void
    {
        // The sandbox helper swaps global container and facade state, exactly
        // as Octane does. Put it back before the next test runs.
        $this->restoreCurrentApplication();

        parent::tearDown();
    }

    /**
     * Build a request sandbox the way Octane's Worker does.
     *
     * A bare `clone` is not enough: the clone's "app" and Container bindings
     * still point at the original object, so anything autowiring a container
     * would silently receive the base application. Octane fixes that in
     * CurrentApplication::set(), and this mirrors it.
     */
    protected function sandbox(): Application
    {
        $sandbox = clone $this->app;

        $sandbox->instance('app', $sandbox);
        $sandbox->instance(Container::class, $sandbox);

        Container::setInstance($sandbox);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($sandbox);

        return $sandbox;
    }

    protected function restoreCurrentApplication(): void
    {
        Container::setInstance($this->app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
    }

    #[Test]
    public function the_transport_is_built_from_the_container_that_resolved_the_mail_manager(): void
    {
        $sandbox = $this->sandbox();

        // A dispatcher unique to the sandbox, as Octane would produce for a
        // service that is not warmed.
        $sandboxEvents = new EventDispatcher($sandbox);
        $sandbox->instance('events', $sandboxEvents);
        $sandbox->instance(Dispatcher::class, $sandboxEvents);

        $stored = [];
        $sandboxEvents->listen(MessageStored::class, function (MessageStored $event) use (&$stored): void {
            $stored[] = $event->message->subject;
        });

        // The base application must not be the one that hears about it.
        $baseHeard = [];
        $this->app->make(Dispatcher::class)->listen(
            MessageStored::class,
            function (MessageStored $event) use (&$baseHeard): void {
                $baseHeard[] = $event->message->subject;
            }
        );

        $sandbox->make(MailManager::class)
            ->mailer('database')
            ->to('rachel@example.test')
            ->send(new OrderShipped('SANDBOX'));

        $this->assertSame(['Order SANDBOX shipped'], $stored,
            'MessageStored must fire on the dispatcher belonging to the container that built the transport.');

        $this->assertSame([], $baseHeard,
            'The base application dispatcher must not receive events from a sandbox request.');
    }

    #[Test]
    public function each_sandbox_gets_its_own_transport_and_they_do_not_leak_into_each_other(): void
    {
        foreach (['first', 'second', 'third'] as $subject) {
            $sandbox = $this->sandbox();

            $sandbox->make(MailManager::class)
                ->mailer('database')
                ->to('rachel@example.test')
                ->send(new OrderShipped($subject));
        }

        $captured = TestMailMessage::query()->orderBy('id')->pluck('subject')->all();

        $this->assertSame(
            ['Order first shipped', 'Order second shipped', 'Order third shipped'],
            $captured
        );

        // Distinct storage per message: a transport reused across requests
        // must never reuse a message's uuid or overwrite its blobs.
        $uuids = TestMailMessage::query()->pluck('uuid')->all();
        $this->assertCount(3, array_unique($uuids));
    }

    #[Test]
    public function repeated_sends_through_one_long_lived_worker_stay_independent(): void
    {
        // A worker handles many requests without rebooting; the transport and
        // recorder are resolved once and must hold no per-message state.
        for ($i = 1; $i <= 5; $i++) {
            Mail::to(sprintf('user%d@example.test', $i))->send(new OrderShipped('A-'.$i));
        }

        $messages = TestMailMessage::query()->orderBy('id')->get();

        $this->assertCount(5, $messages);

        foreach ($messages as $index => $message) {
            $number = $index + 1;

            $this->assertSame(sprintf('Order A-%d shipped', $number), $message->subject);
            $this->assertSame(sprintf('user%d@example.test', $number), $message->to[0]['address']);
            $this->assertTrue($message->hasRaw());
            $this->assertStringContainsString(sprintf('Order A-%d shipped', $number), (string) $message->raw());
        }

        $this->assertCount(5, $messages->pluck('uuid')->unique());
        $this->assertCount(5, $messages->pluck('message_id')->unique());
    }

    #[Test]
    public function the_auth_callback_is_the_only_state_that_outlives_a_request(): void
    {
        // It is deliberately static: consumers set it once from a service
        // provider, which under Octane boots once per worker rather than per
        // request. flushState() exists so a worker can be reset if a consumer
        // ever needs to.
        TestMail::auth(fn ($request): bool => true);

        $this->assertInstanceOf(\Closure::class, TestMail::$authUsing);

        TestMail::flushState();

        $this->assertNull(TestMail::$authUsing);
    }
}
