<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * The message header. Its height is CSS and its disclosure is native, so what
 * is worth pinning here is the split: which fields the summary line carries,
 * and that the rest are still rendered rather than dropped.
 */
class DetailHeaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewMailroom', fn (?User $user): bool => true);
    }

    protected function pageForLatest(): TestResponse
    {
        return $this->get('/mailroom/'.MailroomMessage::query()->latest('id')->firstOrFail()->getKey());
    }

    /**
     * The markup between <summary> and </summary>, which is what a reader sees
     * before opening the disclosure.
     */
    protected function summary(string $html): string
    {
        $this->assertMatchesRegularExpression('#<summary class="mr-meta-line".*?</summary>#s', $html);

        preg_match('#<summary class="mr-meta-line".*?</summary>#s', $html, $found);

        return $found[0];
    }

    /**
     * The disclosure's contents: everything between </summary> and the closing
     * </details>.
     *
     * Scoped deliberately. Asserting against the whole page would prove
     * nothing, because the Headers tab renders every recorded header, so Cc and
     * Bcc appear there whether or not this header still carries them.
     */
    protected function fields(string $html): string
    {
        $this->assertMatchesRegularExpression('#<summary class="mr-meta-line".*?</summary>(.*?)</details>#s', $html);

        preg_match('#<summary class="mr-meta-line".*?</summary>(.*?)</details>#s', $html, $found);

        return $found[1];
    }

    #[Test]
    public function the_summary_line_names_who_the_message_went_to(): void
    {
        Mail::to('rachel@example.test', 'Rachel Okonkwo')->cc('dara@example.test')->send(new OrderShipped);

        $summary = $this->summary($this->pageForLatest()->getContent());

        // Names where there are names, addresses where there are not.
        $this->assertStringContainsString('to Rachel Okonkwo, cc dara@example.test', $summary);
        $this->assertStringNotContainsString('rachel@example.test', $summary);
    }

    #[Test]
    public function the_date_sits_beside_the_summary_and_the_size_inside_it(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped);

        $message = MailroomMessage::query()->latest('id')->firstOrFail();
        $html = $this->pageForLatest()->getContent();
        $sent = $message->sent_at?->toDayDateTimeString() ?? $message->created_at?->toDayDateTimeString();

        $this->assertMatchesRegularExpression('#<div class="mr-when">\s*'.preg_quote((string) $sent, '#').'\s*</div>#', $html);

        /*
         * The size is reference data rather than something anyone scans a
         * mailbox for, so it belongs to the grid. Keeping it off the line is
         * what leaves the recipients room before they truncate.
         */
        $this->assertStringNotContainsString($message->humanSize(), $this->summary($html));
        $this->assertStringContainsString($message->humanSize(), $this->fields($html));
    }

    #[Test]
    public function the_recipients_are_wrapped_so_they_can_truncate(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped);

        $summary = $this->summary($this->pageForLatest()->getContent());

        /*
         * .mr-meta-to is what the recipients truncate against. Move them out
         * of it and a long recipient list wraps the header onto a second line
         * instead of ending in an ellipsis, which nothing else here would
         * catch.
         */
        $this->assertMatchesRegularExpression('#<span class="mr-meta-to">[^<]*rachel@example\.test[^<]*</span>#', $summary);
    }

    #[Test]
    public function the_disclosure_is_closed_until_asked(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped);

        // A stored preference reopens it client side. Server side it always
        // renders shut, so nothing depends on JavaScript to be readable.
        $this->pageForLatest()->assertDontSeeHtml('<details class="mr-meta" id="mr-meta" open>');
    }

    #[Test]
    public function bcc_and_reply_to_wait_behind_the_disclosure(): void
    {
        Mail::to('rachel@example.test')
            ->cc('accounts@example.test')
            ->bcc('audit@example.test')
            ->send((new OrderShipped)->replyTo('support@example.test'));

        $html = $this->pageForLatest()->getContent();
        $summary = $this->summary($html);
        $fields = $this->fields($html);

        foreach (['rachel@example.test', 'accounts@example.test', 'audit@example.test', 'support@example.test'] as $address) {
            $this->assertStringContainsString($address, $fields, "{$address} should be inside the disclosure.");
        }

        foreach (['audit@example.test', 'support@example.test'] as $address) {
            $this->assertStringNotContainsString($address, $summary, "{$address} should not be on the summary line.");
        }
    }
}
