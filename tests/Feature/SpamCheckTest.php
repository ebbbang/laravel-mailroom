<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Mailroom;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

/**
 * The spam check is the only place captured mail leaves the application, so
 * most of what is pinned here is about when it does not happen.
 */
class SpamCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The permissive shape a consumer might reach for on staging, which is
        // the situation the second guard exists for: reading the mailbox must
        // not imply the right to hand a message to a third party.
        Mailroom::auth(fn ($request): bool => true);

        // Anything unfaked would be a real request to Postmark from the suite.
        Http::preventStrayRequests();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Set before boot: the route is only registered when a driver is
        // configured, so it cannot be switched on mid-test.
        $app['config']->set('mailroom.spam.driver', 'postmark');
        $app['config']->set('session.driver', 'array');
    }

    protected function capture(string $order = 'A-1001'): MailroomMessage
    {
        Mail::to('rachel@example.test')->send(new OrderShipped($order));

        return MailroomMessage::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function fakePostmark(array $payload, int $status = 200): void
    {
        Http::fake(['spamcheck.postmarkapp.com/*' => Http::response($payload, $status)]);
    }

    /**
     * A reply in the shape Postmark actually sends: every score a string, the
     * rules carrying a description and no name, and the names available only in
     * the report.
     *
     * @return array<string, mixed>
     */
    protected function scored(float $score = 2.7): array
    {
        return [
            'success' => true,
            'score' => (string) $score,
            'rules' => [
                ['score' => '1.4', 'description' => 'Missing Date: header'],
                ['score' => '0.1', 'description' => 'Message only has text/html MIME parts'],
            ],
            'report' => " pts rule                   description\n"
                ."---- ---------------------- --------------------------------\n"
                ." 1.4 MISSING_DATE           Missing Date: header\n"
                ." 0.1 MIME_HTML_ONLY         Message only has text/html MIME parts\n",
        ];
    }

    #[Test]
    public function opening_a_message_sends_nothing(): void
    {
        Http::fake();

        $message = $this->capture();

        $html = $this->actingAs(new User)->get('/mailroom/'.$message->id)->assertOk()->getContent();

        Http::assertNothingSent();
        $this->assertStringContainsString('Not checked yet', $html);
    }

    #[Test]
    public function a_check_posts_the_stored_message_and_keeps_what_comes_back(): void
    {
        $this->fakePostmark($this->scored());

        $message = $this->capture();
        $raw = $message->raw();

        $this->actingAs(new User)
            ->from('/mailroom/'.$message->id)
            ->post('/mailroom/'.$message->id.'/spam-check')
            ->assertRedirect('/mailroom/'.$message->id);

        // The stored bytes, verbatim: scoring a rebuilt approximation would
        // score something the application never produced.
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://spamcheck.postmarkapp.com/filter'
            && $request['email'] === $raw
            && $request['options'] === 'long');

        $message->refresh();

        $this->assertSame(2.7, $message->spam_score);
        $this->assertNotNull($message->spam_checked_at);

        // The name comes from the report, since the rule objects do not carry
        // one, and it is the half worth showing.
        $this->assertSame('MISSING_DATE', $message->spam_rules[0]['name']);
        $this->assertSame(1.4, $message->spam_rules[0]['score']);
        $this->assertSame('Missing Date: header', $message->spam_rules[0]['description']);

        $html = $this->actingAs(new User)->get('/mailroom/'.$message->id)->assertOk()->getContent();

        $this->assertStringContainsString('2.7', $html);
        $this->assertStringContainsString('MISSING_DATE', $html);
    }

    #[Test]
    public function checking_again_replaces_the_previous_result(): void
    {
        Http::fake(['spamcheck.postmarkapp.com/*' => Http::sequence()
            ->push($this->scored(7.4))
            ->push($this->scored(1.1))]);

        $message = $this->capture();

        $this->actingAs(new User)->post('/mailroom/'.$message->id.'/spam-check');
        $this->actingAs(new User)->post('/mailroom/'.$message->id.'/spam-check');

        // One score per message, and it is the current one. Two would only
        // raise the question of which still applies.
        $this->assertSame(1.1, $message->refresh()->spam_score);
    }

    #[Test]
    public function a_refusal_is_shown_and_nothing_is_stored(): void
    {
        $this->fakePostmark(['success' => false, 'message' => 'Message is too large']);

        $message = $this->capture();

        $this->actingAs(new User)
            ->post('/mailroom/'.$message->id.'/spam-check')
            ->assertSessionHas('mailroom.error', fn (string $error): bool => str_contains($error, 'Message is too large'));

        $this->assertNull($message->refresh()->spam_checked_at);
    }

    #[Test]
    public function a_missing_stored_copy_is_explained_without_a_request(): void
    {
        Http::fake();

        $message = $this->capture();

        Storage::disk('local')->delete($message->raw_path);

        $this->actingAs(new User)
            ->post('/mailroom/'.$message->id.'/spam-check')
            ->assertSessionHas('mailroom.error', fn (string $error): bool => str_contains($error, 'stored copy'));

        Http::assertNothingSent();
        $this->assertNull($message->refresh()->spam_checked_at);
    }

    #[Test]
    public function it_refuses_without_a_signed_in_user(): void
    {
        Http::fake();

        $message = $this->capture();

        $this->post('/mailroom/'.$message->id.'/spam-check')->assertForbidden();

        Http::assertNothingSent();
        $this->assertNull($message->refresh()->spam_checked_at);

        // The pane says why instead of offering a button that would refuse.
        $this->get('/mailroom/'.$message->id)
            ->assertOk()
            ->assertSee('Checking needs a signed-in user');
    }

    #[Test]
    public function the_rate_limit_refuses_past_the_ceiling(): void
    {
        $this->fakePostmark($this->scored());

        config()->set('mailroom.spam.rate_limit', 1);

        $message = $this->capture();

        $this->actingAs($user = new User)->post('/mailroom/'.$message->id.'/spam-check');

        $this->actingAs($user)
            ->post('/mailroom/'.$message->id.'/spam-check')
            ->assertSessionHas('mailroom.error', fn (string $error): bool => str_contains($error, 'spam checks in one minute'));
    }
}
