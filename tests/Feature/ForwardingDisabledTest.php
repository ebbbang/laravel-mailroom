<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Mailroom;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

/**
 * The shipped default: no forwarding mailer configured. Nothing about the
 * feature should exist until someone sets one up, so this is the state every
 * consumer is in the moment they install the package.
 */
class ForwardingDisabledTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mailroom::auth(fn ($request): bool => true);
    }

    #[Test]
    public function no_mailer_is_configured_by_default(): void
    {
        $this->assertNull(config('mailroom.forward.mailer'));
        $this->assertFalse(Mailroom::canForward());
    }

    #[Test]
    public function the_route_does_not_exist(): void
    {
        // Not merely guarded -- absent. There is no endpoint to probe.
        $this->assertFalse(Route::has('mailroom.forward'));
    }

    #[Test]
    public function posting_to_the_path_anyway_finds_nothing(): void
    {
        $message = $this->capture();

        $this->post('/mailroom/'.$message->id.'/forward', ['to' => 'qa@team.test'])
            ->assertNotFound();
    }

    #[Test]
    public function the_message_view_still_shows_the_button_and_explains_how_to_enable_it(): void
    {
        /*
         * Discoverability is the reason the button is rendered even here:
         * nobody finds a feature whose only trace is a line in the README. The
         * dialog behind it explains the setup rather than offering a form, and
         * the route is still absent, so showing it opens nothing.
         */
        $message = $this->capture();

        $response = $this->get(route('mailroom.show', $message));

        $response->assertOk();
        $response->assertSeeHtml('id="mr-forward-open"');
        $response->assertSeeHtml('MAILROOM_FORWARD_MAILER');

        // No form to submit, because there is nowhere to submit it to.
        $response->assertDontSeeHtml('id="mr-forward-form"');
    }

    protected function capture(): MailroomMessage
    {
        Mail::to('rachel@example.test')->send(new OrderShipped('A-1001'));

        return MailroomMessage::query()->latest('id')->firstOrFail();
    }
}
