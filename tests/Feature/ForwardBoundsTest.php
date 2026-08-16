<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Mailroom;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\Fixtures\RelaySpy;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * The two bounds a shared staging box needs: where a message may be sent, and
 * how often. Both are absent by default, so these also pin that an existing
 * install keeps behaving exactly as it did before this release.
 */
class ForwardBoundsTest extends TestCase
{
    protected RelaySpy $relay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->relay = new RelaySpy;

        Mail::extend('relay-spy', fn (): TransportInterface => $this->relay);
        Mailroom::auth(fn ($request): bool => true);

        // Counting lives in the cache, so it would otherwise carry between
        // tests in the same process and make the order they run in matter.
        RateLimiter::clear('mailroom-forward');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('mail.mailers.relay', ['transport' => 'relay-spy']);
        $app['config']->set('mailroom.forward.mailer', 'relay');
        $app['config']->set('session.driver', 'array');
    }

    protected function capture(): MailroomMessage
    {
        Mail::to('rachel@example.test')->send(new OrderShipped('A-1001'));

        return MailroomMessage::query()->latest('id')->firstOrFail();
    }

    protected function forward(MailroomMessage $message, string $to): TestResponse
    {
        return $this->actingAs(new User)
            ->post(route('mailroom.forward', $message), ['to' => $to]);
    }

    #[Test]
    public function an_empty_allowlist_permits_anywhere(): void
    {
        // The shipped default, and what every 0.4.0 install is running.
        $this->assertSame([], config('mailroom.forward.allowed'));

        $message = $this->capture();

        $this->forward($message, 'anyone@anywhere.test')->assertSessionHasNoErrors();

        $this->assertCount(1, $this->relay->sent);
    }

    #[Test]
    public function an_address_outside_the_allowlist_is_refused(): void
    {
        config()->set('mailroom.forward.allowed', ['@yourco.com']);

        $message = $this->capture();

        $this->forward($message, 'someone@elsewhere.test')->assertSessionHasErrors('to');

        $this->assertSame([], $this->relay->sent);
    }

    #[Test]
    public function an_address_inside_the_allowlist_goes_through(): void
    {
        config()->set('mailroom.forward.allowed', ['@yourco.com', 'contractor@partner.test']);

        $message = $this->capture();

        $this->forward($message, 'qa@yourco.com')->assertSessionHasNoErrors();
        $this->forward($message, 'contractor@partner.test')->assertSessionHasNoErrors();

        $this->assertCount(2, $this->relay->sent);
    }

    #[Test]
    public function a_refusal_reopens_the_dialog_with_the_address_still_there(): void
    {
        config()->set('mailroom.forward.allowed', ['@yourco.com']);

        $message = $this->capture();

        $this->forward($message, 'someone@elsewhere.test');

        $response = $this->actingAs(new User)->get(route('mailroom.show', $message));

        $response->assertSeeHtml('data-mr-open');
        $response->assertSeeHtml('mr-modal-error');
        // Naming what is permitted is the difference between a dead end and
        // something the reader can act on.
        $response->assertSeeHtml('@yourco.com');
    }

    #[Test]
    public function forwarding_stops_once_the_rate_limit_is_reached(): void
    {
        config()->set('mailroom.forward.rate_limit', 3);

        $message = $this->capture();

        foreach (range(1, 3) as $ignored) {
            $this->forward($message, 'qa@yourco.com')->assertSessionHasNoErrors();
        }

        // A browser-facing 429 would drop the reader out of the mailbox; this
        // has to come back as the dialog explaining itself, like every other
        // forwarding failure.
        $this->forward($message, 'qa@yourco.com')
            ->assertRedirect()
            ->assertSessionHas('mailroom.error');

        $this->assertCount(3, $this->relay->sent);
    }

    #[Test]
    public function a_null_rate_limit_removes_the_ceiling(): void
    {
        // Array form keeps the null visible; passing it positionally is the
        // Config::set default, so rector strips it and the test stops showing
        // the case it exists to cover.
        config()->set(['mailroom.forward.rate_limit' => null]);

        $message = $this->capture();

        foreach (range(1, 15) as $ignored) {
            $this->forward($message, 'qa@yourco.com');
        }

        $this->assertCount(15, $this->relay->sent);
    }

    #[Test]
    public function the_limit_is_counted_per_user_rather_than_globally(): void
    {
        config()->set('mailroom.forward.rate_limit', 2);

        $message = $this->capture();

        $first = new User;
        $first->setAttribute('id', 1);

        $second = new User;
        $second->setAttribute('id', 2);

        foreach ([$first, $first] as $user) {
            $this->actingAs($user)->post(route('mailroom.forward', $message), ['to' => 'qa@yourco.com']);
        }

        // The first person has used their allowance; the second must still
        // have theirs, or one busy tester locks out the whole team.
        $this->actingAs($second)
            ->post(route('mailroom.forward', $message), ['to' => 'qa@yourco.com'])
            ->assertSessionHasNoErrors();

        $this->assertCount(3, $this->relay->sent);
    }

    #[Test]
    public function the_route_carries_the_throttle_middleware(): void
    {
        $middleware = Route::getRoutes()->getByName('mailroom.forward')->gatherMiddleware();

        $this->assertContains('throttle:mailroom-forward', $middleware);
    }
}
