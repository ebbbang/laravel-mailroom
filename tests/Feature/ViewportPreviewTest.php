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
 * The preview width switch. The widths themselves are CSS and the switching is
 * client side, so what is worth pinning here is where the state attribute is
 * allowed to appear -- which is what keeps the Raw pane out of it.
 */
class ViewportPreviewTest extends TestCase
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
     * The value of data-mr-viewport on one pane, or null when it carries none.
     * Read off the opening tag rather than by counting occurrences, so neither
     * attribute order nor the inlined stylesheet can affect the result.
     */
    protected function paneAttribute(string $html, string $pane): ?string
    {
        preg_match('/<div[^>]*data-mr-pane="'.$pane.'"[^>]*>/', $html, $tag);

        $this->assertNotEmpty($tag, "No [data-mr-pane=\"{$pane}\"] element was rendered.");

        return preg_match('/data-mr-viewport="([^"]*)"/', $tag[0], $found) === 1
            ? $found[1]
            : null;
    }

    #[Test]
    public function a_message_with_an_html_body_gets_the_width_switch(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped);

        $response = $this->pageForLatest();

        $response->assertSeeHtml('data-mr-viewport-choice="desktop"');
        $response->assertSeeHtml('data-mr-viewport-choice="tablet"');
        $response->assertSeeHtml('data-mr-viewport-choice="mobile"');

        // Desktop is the default, so the pane opens at the full width it had
        // before this control existed.
        $response->assertSeeHtml('data-mr-viewport="desktop"');
    }

    #[Test]
    public function a_message_with_no_html_body_gets_no_width_switch(): void
    {
        Mail::raw('Text only, so there is no frame to resize.', function ($message): void {
            $message->to('rachel@example.test')->subject('Text only');
        });

        $response = $this->pageForLatest();

        /*
         * Not "data-mr-viewport": the stylesheet is inlined into every page,
         * and it carries the width selectors whether or not this message has a
         * frame to apply them to. The -choice suffix appears only in the
         * control's markup and its script, which is what has to be absent.
         */
        $response->assertDontSeeHtml('data-mr-viewport-choice');
    }

    #[Test]
    public function the_raw_pane_is_left_out_of_it(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped);

        $message = MailroomMessage::query()->latest('id')->firstOrFail();

        // Both panes render an .mr-frame, so a width rule written against that
        // class rather than against the pane would narrow the raw source too.
        $this->assertTrue($message->hasRaw());

        $html = $this->pageForLatest()->getContent();

        $this->assertSame(
            'desktop',
            $this->paneAttribute($html, 'html'),
            'The HTML pane should carry the preview width.'
        );

        $this->assertNull(
            $this->paneAttribute($html, 'raw'),
            'The raw source is not a rendered body and must never be resized.'
        );
    }
}
