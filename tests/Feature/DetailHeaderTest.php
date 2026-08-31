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
        $this->assertMatchesRegularExpression('#<summary\b.*?</summary>#s', $html);

        preg_match('#<summary\b.*?</summary>#s', $html, $found);

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
        $this->assertMatchesRegularExpression('#</summary>.*?</details>#s', $html);

        preg_match('#</summary>.*?</details>#s', $html, $found);

        return $found[0];
    }

    #[Test]
    public function the_summary_line_carries_who_to_whom_and_when(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped);

        $message = MailroomMessage::query()->latest('id')->firstOrFail();
        $html = $this->pageForLatest()->getContent();
        $summary = $this->summary($html);

        $this->assertStringContainsString('rachel@example.test', $summary);
        $this->assertStringContainsString(
            $message->sent_at?->toDayDateTimeString() ?? $message->created_at?->toDayDateTimeString(),
            $summary
        );

        /*
         * And no further. The size is reference data rather than something
         * anyone scans a mailbox for, so it belongs to the grid; the mailer is
         * already on every row of the list. Keeping them off this line is what
         * leaves the addresses room before they truncate.
         */
        $this->assertStringNotContainsString($message->humanSize(), $summary);
        $this->assertStringContainsString($message->humanSize(), $this->fields($html));
    }

    #[Test]
    public function the_abbreviated_line_is_wrapped_so_it_can_truncate_and_be_swapped_out(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped);

        $summary = $this->summary($this->pageForLatest()->getContent());

        /*
         * The addresses sit inside .mr-meta-inline rather than loose in the
         * summary, and that wrapper does two jobs: it is what they truncate
         * against, and it is what CSS hides once the grid below is showing.
         * Move them out and the ellipsis quietly stops working while the open
         * state starts repeating itself, neither of which anything else here
         * would catch.
         */
        preg_match('#<span class="mr-meta-inline">.*?</span>\s*</span>#s', $summary, $inline);

        $this->assertNotEmpty($inline, 'The summary should wrap its meta spans in .mr-meta-inline.');
        $this->assertStringContainsString('rachel@example.test', $inline[0]);
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
    public function the_crowded_fields_move_behind_the_disclosure_rather_than_away(): void
    {
        Mail::to('rachel@example.test')
            ->cc('accounts@example.test')
            ->bcc('audit@example.test')
            ->send((new OrderShipped)->replyTo('support@example.test'));

        $html = $this->pageForLatest()->getContent();
        $summary = $this->summary($html);
        $fields = $this->fields($html);

        foreach (['accounts@example.test', 'audit@example.test', 'support@example.test'] as $address) {
            // Still in the header, which is the half that says nothing was
            // dropped on the way behind the disclosure.
            $this->assertStringContainsString($address, $fields, "{$address} should be inside the disclosure.");

            // And not on the collapsed line, which is the half that says the
            // header stays one row tall however many addresses a message has.
            $this->assertStringNotContainsString($address, $summary, "{$address} should not be on the summary line.");
        }
    }
}
