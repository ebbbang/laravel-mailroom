<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Mailroom;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

/**
 * The shipped default: no spam driver configured. Since this is the one feature
 * that would send captured mail to a third party, nothing about it should be
 * reachable until somebody deliberately turns it on.
 */
class SpamCheckDisabledTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mailroom::auth(fn ($request): bool => true);

        Http::preventStrayRequests();
    }

    #[Test]
    public function no_driver_is_configured_by_default(): void
    {
        $this->assertNull(config('mailroom.spam.driver'));
        $this->assertFalse(Mailroom::canSpamCheck());
    }

    #[Test]
    public function the_route_does_not_exist(): void
    {
        // Not merely guarded: absent. There is no endpoint to probe.
        $this->assertFalse(Route::has('mailroom.spam-check'));
    }

    #[Test]
    public function the_pane_says_how_to_switch_it_on(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped);

        $message = MailroomMessage::query()->latest('id')->firstOrFail();

        // The same reasoning as the Forward button: a feature whose only trace
        // is a line in the README is a feature nobody finds.
        $this->get('/mailroom/'.$message->id)
            ->assertOk()
            ->assertSeeHtml('MAILROOM_SPAM_DRIVER=postmark');
    }
}
