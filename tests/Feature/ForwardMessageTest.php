<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Mailroom;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

class ForwardMessageTest extends TestCase
{
    protected RelaySpy $relay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->relay = new RelaySpy;

        Mail::extend('relay-spy', fn (): TransportInterface => $this->relay);

        // Let every test past the mailbox gate. This is deliberately the
        // permissive shape a consumer might reach for on staging, which is
        // exactly the situation the forwarding guard has to hold up in: being
        // able to read the mailbox must not imply being able to send from it.
        Mailroom::auth(fn ($request): bool => true);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Set before boot: the route is only registered when a forwarding
        // mailer is configured, so this cannot be switched on mid-test.
        $app['config']->set('mail.mailers.relay', ['transport' => 'relay-spy']);
        $app['config']->set('mailroom.forward.mailer', 'relay');

        // The cookie driver needs a live request before it can be read, which
        // the local-environment test does not have when it seeds a CSRF token.
        $app['config']->set('session.driver', 'array');
    }

    protected function capture(string $order = 'A-1001'): MailroomMessage
    {
        Mail::to('rachel@example.test')->send(new OrderShipped($order));

        return MailroomMessage::query()->latest('id')->firstOrFail();
    }

    /**
     * Forwarding needs a real user outside local, which is the whole point of
     * the guard, so the happy paths sign in.
     */
    protected function asUser(): static
    {
        return $this->actingAs(new User);
    }

    #[Test]
    public function forwarding_to_the_original_recipient_sends_the_stored_bytes_untouched(): void
    {
        $message = $this->capture();

        $this->asUser()
            ->post(route('mailroom.forward', $message), ['to' => 'rachel@example.test'])
            ->assertRedirect()
            ->assertSessionHas('mailroom.status');

        $this->assertCount(1, $this->relay->sent);

        // Byte for byte. What the tester opens in Gmail is exactly what the
        // application produced -- that is the reason to replay raw MIME rather
        // than rebuild the message.
        $this->assertSame($message->raw(), $this->relay->body(0));
    }

    #[Test]
    public function it_delivers_only_to_the_chosen_address(): void
    {
        $message = $this->capture();

        $this->asUser()->post(route('mailroom.forward', $message), ['to' => 'rachel@example.test']);

        $recipients = array_map(
            fn (Address $address): string => $address->getAddress(),
            $this->relay->sent[0]['envelope']->getRecipients()
        );

        $this->assertSame(['rachel@example.test'], $recipients);
    }

    #[Test]
    public function forwarding_elsewhere_retargets_the_headers(): void
    {
        $message = $this->capture();

        $this->asUser()
            ->post(route('mailroom.forward', $message), ['to' => 'qa@team.test'])
            ->assertSessionHas('mailroom.status');

        $sent = $this->relay->body(0);

        $this->assertStringContainsString('To: qa@team.test', $sent);
        $this->assertStringContainsString('X-Mailroom-Original-To: rachel@example.test', $sent);
        $this->assertNotSame($message->raw(), $sent);
    }

    #[Test]
    public function the_envelope_sender_is_the_one_that_was_recorded(): void
    {
        $message = $this->capture();

        $this->asUser()->post(route('mailroom.forward', $message), ['to' => 'qa@team.test']);

        $this->assertSame(
            $message->envelope_sender,
            $this->relay->sent[0]['envelope']->getSender()->getAddress()
        );
    }

    #[Test]
    public function it_refuses_without_an_authenticated_user_outside_local(): void
    {
        /*
         * The guard that matters. A consumer can open the mailbox up with a
         * permissive auth callback -- an IP allowlist, say -- and that grants
         * reading. It must not also grant the ability to relay mail from their
         * domain, so this asserts a *reader* is still refused.
         */
        $message = $this->capture();

        $this->post(route('mailroom.forward', $message), ['to' => 'qa@team.test'])
            ->assertForbidden();

        $this->assertSame([], $this->relay->sent);
    }

    #[Test]
    public function it_allows_an_unauthenticated_forward_in_local_development(): void
    {
        app()->detectEnvironment(fn (): string => 'local');

        $message = $this->capture();

        /*
         * Forcing the environment also stops Laravel recognising this as a
         * test run, so CSRF protection engages for real. Supplying a token is
         * more faithful than switching the middleware off, and it keeps the
         * assertion about the forwarding guard rather than about middleware.
         */
        $this->withSession(['_token' => 'test-token'])
            ->post(route('mailroom.forward', $message), [
                '_token' => 'test-token',
                'to' => 'qa@team.test',
            ])
            ->assertRedirect();

        $this->assertCount(1, $this->relay->sent);
    }

    #[Test]
    public function the_auth_requirement_can_be_switched_off_deliberately(): void
    {
        config()->set('mailroom.forward.require_authenticated_user', false);

        $message = $this->capture();

        $this->post(route('mailroom.forward', $message), ['to' => 'qa@team.test'])
            ->assertRedirect();

        $this->assertCount(1, $this->relay->sent);
    }

    #[Test]
    public function it_rejects_an_address_that_is_not_an_address(): void
    {
        $message = $this->capture();

        $this->asUser()
            ->post(route('mailroom.forward', $message), ['to' => 'not-an-address'])
            ->assertSessionHasErrors('to');

        $this->assertSame([], $this->relay->sent);
    }

    #[Test]
    public function a_failure_reopens_the_dialog_rather_than_shifting_the_page(): void
    {
        // Reporting a failure by inserting a strip into the layout pushed
        // everything down, moving whatever you were looking at out from under
        // the cursor. The dialog reopens instead, with the message beside the
        // field you would change to put it right.
        $message = $this->capture();

        $this->asUser()
            ->post(route('mailroom.forward', $message), ['to' => 'not-an-address'])
            ->assertSessionHasErrors('to');

        $this->asUser()
            ->get(route('mailroom.show', $message))
            ->assertSeeHtml('data-mr-open')
            ->assertSeeHtml('mr-modal-error');
    }

    #[Test]
    public function it_explains_itself_when_the_stored_copy_is_gone(): void
    {
        $message = $this->capture();

        Storage::disk('local')->delete($message->raw_path);

        $this->asUser()
            ->post(route('mailroom.forward', $message), ['to' => 'qa@team.test'])
            ->assertRedirect()
            ->assertSessionHas('mailroom.error');

        $this->assertSame([], $this->relay->sent);
    }

    #[Test]
    public function it_refuses_to_forward_through_another_mailroom_mailer(): void
    {
        // Would capture the message a second time instead of delivering it,
        // leaving the tester waiting for mail that was never going to arrive.
        config()->set('mail.mailers.relay', ['transport' => 'mailroom']);

        $message = $this->capture();

        $this->asUser()
            ->post(route('mailroom.forward', $message), ['to' => 'qa@team.test'])
            ->assertSessionHas('mailroom.error');
    }

    #[Test]
    public function it_reports_a_relay_that_will_not_accept_the_message(): void
    {
        $this->relay->refuseWith('Connection refused');

        $message = $this->capture();

        $this->asUser()
            ->post(route('mailroom.forward', $message), ['to' => 'qa@team.test'])
            ->assertRedirect()
            ->assertSessionHas('mailroom.error');

        // The capture is the source of truth, so a failed relay must not take
        // the stored message with it.
        $this->assertTrue($message->fresh()->hasRaw());
        $this->assertSame(1, MailroomMessage::query()->count());
    }

    #[Test]
    public function forwarding_does_not_capture_the_message_again(): void
    {
        $message = $this->capture();

        $this->asUser()->post(route('mailroom.forward', $message), ['to' => 'qa@team.test']);

        // The forwarding mailer is a real transport, not a mailroom one, so
        // nothing new lands in the mailbox and the original is untouched.
        $this->assertSame(1, MailroomMessage::query()->count());
        $this->assertSame($message->uuid, MailroomMessage::sole()->uuid);
    }
}

/**
 * Stands in for a real relay so the tests can read exactly what would have
 * gone out, envelope included.
 */
class RelaySpy implements TransportInterface
{
    /** @var array<int, array{message: RawMessage, envelope: Envelope}> */
    public array $sent = [];

    protected ?string $failure = null;

    public function refuseWith(string $message): void
    {
        $this->failure = $message;
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($this->failure !== null) {
            throw new \RuntimeException($this->failure);
        }

        $envelope ??= new Envelope(new Address('app@example.test'), [new Address('nobody@example.test')]);

        $this->sent[] = ['message' => $message, 'envelope' => $envelope];

        return new SentMessage($message, $envelope);
    }

    public function body(int $index): string
    {
        return $this->sent[$index]['message']->toString();
    }

    public function __toString(): string
    {
        return 'relay-spy';
    }
}
