<?php

namespace Ebbbang\TestMail\Tests\Feature;

use Ebbbang\TestMail\Exceptions\TestMailDisabledException;
use Ebbbang\TestMail\Tests\Fixtures\OrderShipped;
use Ebbbang\TestMail\Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

class ProductionGuardTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Simulate a production deploy that never opted in.
        $app['config']->set('test-mail.enabled', false);
    }

    #[Test]
    public function it_refuses_to_build_the_transport_when_disabled(): void
    {
        $this->expectException(TestMailDisabledException::class);
        $this->expectExceptionMessageMatches('/TEST_MAIL_ENABLED=true/');

        Mail::to('rachel@example.test')->send(new OrderShipped);
    }

    #[Test]
    public function it_fails_loudly_rather_than_swallowing_the_message(): void
    {
        // The important property: a disabled package must never accept mail
        // and quietly drop it, which would look like successful delivery.
        try {
            Mail::to('rachel@example.test')->send(new OrderShipped);
            $this->fail('Sending should have thrown while the package is disabled.');
        } catch (TestMailDisabledException $testMailDisabledException) {
            $this->assertStringContainsString('disabled', $testMailDisabledException->getMessage());
        }
    }

    #[Test]
    public function it_does_not_register_the_mailbox_routes_when_disabled(): void
    {
        $this->get('/test-mail')->assertNotFound();
    }
}
