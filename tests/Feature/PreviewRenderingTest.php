<?php

namespace Ebbbang\TestMail\Tests\Feature;

use Ebbbang\TestMail\Models\TestMailAttachment;
use Ebbbang\TestMail\Models\TestMailMessage;
use Ebbbang\TestMail\Support\PreviewKind;
use Ebbbang\TestMail\Support\TextPreview;
use Ebbbang\TestMail\Tests\Fixtures\OrderShipped;
use Ebbbang\TestMail\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

class PreviewRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewTestMail', fn (?User $user): bool => true);
    }

    protected function attach(string $contents, string $name, string $mime): TestMailAttachment
    {
        Mail::to('rachel@example.test')->send(
            (new OrderShipped)->attachData($contents, $name, ['mime' => $mime])
        );

        return TestMailMessage::query()->latest('id')->first()->attachments()->sole();
    }

    /**
     * @return array<string, mixed>
     */
    protected function render(string $contents, string $name, string $mime): array
    {
        return resolve(TextPreview::class)->render($this->attach($contents, $name, $mime));
    }

    protected function pageFor(TestMailAttachment $attachment): TestResponse
    {
        return $this->get('/test-mail/'.$attachment->test_mail_message_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Text kinds
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_renders_plain_text_as_is(): void
    {
        $rendered = $this->render("line one\nline two", 'notes.txt', 'text/plain');

        $this->assertSame('ok', $rendered['state']);
        $this->assertSame("line one\nline two", $rendered['body']);
        $this->assertNull($rendered['rows']);
    }

    #[Test]
    public function it_normalises_windows_newlines(): void
    {
        $rendered = $this->render("one\r\ntwo\rthree", 'notes.txt', 'text/plain');

        $this->assertSame("one\ntwo\nthree", $rendered['body']);
    }

    #[Test]
    public function it_parses_csv_into_rows(): void
    {
        $rendered = $this->render("name,total\nRachel,49.00\nSam,\"1,299.00\"", 'orders.csv', 'text/csv');

        $this->assertSame('ok', $rendered['state']);
        $this->assertSame([
            ['name', 'total'],
            ['Rachel', '49.00'],
            ['Sam', '1,299.00'],
        ], $rendered['rows']);
    }

    #[Test]
    public function it_detects_a_semicolon_delimiter(): void
    {
        $rendered = $this->render("name;total\nRachel;49.00", 'orders.csv', 'text/csv');

        $this->assertSame([['name', 'total'], ['Rachel', '49.00']], $rendered['rows']);
    }

    #[Test]
    public function it_caps_csv_rows_and_says_so(): void
    {
        config()->set('test-mail.preview.max_csv_rows', 5);

        $lines = ['name'];
        for ($i = 1; $i <= 20; $i++) {
            $lines[] = 'row-'.$i;
        }

        $rendered = $this->render(implode("\n", $lines), 'big.csv', 'text/csv');

        $this->assertCount(5, $rendered['rows']);
        $this->assertStringContainsString('first 5 of 21 rows', $rendered['notes'][0]);
    }

    #[Test]
    public function it_pretty_prints_json(): void
    {
        $rendered = $this->render('{"order":"A-1","total":49}', 'order.json', 'application/json');

        $this->assertSame('ok', $rendered['state']);
        $this->assertStringContainsString("{\n    \"order\": \"A-1\"", $rendered['body']);
        $this->assertSame([], $rendered['notes']);
    }

    #[Test]
    public function invalid_json_shows_the_raw_text_and_explains_why(): void
    {
        // Malformed JSON in a test email is a finding, not something to hide.
        $rendered = $this->render('{"order": broken}', 'order.json', 'application/json');

        $this->assertSame('ok', $rendered['state']);
        $this->assertSame('{"order": broken}', $rendered['body']);
        $this->assertStringContainsString('Not valid JSON', $rendered['notes'][0]);
    }

    #[Test]
    public function it_extracts_calendar_event_fields(): void
    {
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'BEGIN:VEVENT',
            'SUMMARY:Delivery window',
            'DTSTART;TZID=Europe/London:20260801T090000',
            'LOCATION:14 Example Street\, London',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        $rendered = $this->render($ics, 'invite.ics', 'text/calendar');

        $this->assertSame('Delivery window', $rendered['fields']['Summary']);
        $this->assertSame('20260801T090000', $rendered['fields']['Starts']);
        $this->assertSame('14 Example Street, London', $rendered['fields']['Location']);
        $this->assertStringContainsString('BEGIN:VEVENT', $rendered['body']);
    }

    #[Test]
    public function it_parses_the_headers_of_a_nested_message(): void
    {
        $eml = "From: app@example.test\r\nTo: rachel@example.test\r\nSubject: Nested\r\n\r\nThe body.";

        $rendered = $this->render($eml, 'forwarded.eml', 'message/rfc822');

        $this->assertSame('app@example.test', $rendered['fields']['From']);
        $this->assertSame('Nested', $rendered['fields']['Subject']);
        $this->assertSame('The body.', $rendered['body']);
    }

    #[Test]
    public function oversized_text_is_refused_rather_than_truncated(): void
    {
        config()->set('test-mail.preview.max_inline_bytes', 64);

        $rendered = $this->render(str_repeat('a', 500), 'big.txt', 'text/plain');

        $this->assertSame('too-large', $rendered['state']);
        $this->assertNull($rendered['body']);
        $this->assertStringContainsString('over the', $rendered['notes'][0]);
    }

    #[Test]
    public function an_empty_file_says_it_is_empty(): void
    {
        $rendered = $this->render("   \n  ", 'blank.txt', 'text/plain');

        $this->assertSame('empty', $rendered['state']);
    }

    /*
    |--------------------------------------------------------------------------
    | The rendered mailbox
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function an_html_attachment_appears_as_escaped_source_not_markup(): void
    {
        $attachment = $this->attach('<script>alert(1)</script>', 'evil.html', 'text/html');

        $response = $this->pageFor($attachment)->assertOk();

        // Present as text...
        $response->assertSee('<script>alert(1)</script>');

        // ...but never as a live tag.
        $response->assertDontSeeHtml('<script>alert(1)</script>');
    }

    #[Test]
    public function a_previewable_attachment_becomes_a_lightbox_trigger(): void
    {
        $attachment = $this->attach('hello', 'notes.txt', 'text/plain');

        $this->pageFor($attachment)
            ->assertOk()
            ->assertSeeHtml('data-tm-preview="0"')
            ->assertSeeHtml('data-tm-preview-content="0"')
            ->assertSeeHtml('id="tm-lightbox"');
    }

    #[Test]
    public function an_office_document_offers_a_download_and_says_there_is_no_preview(): void
    {
        $attachment = $this->attach(
            'PK fake docx',
            'invoice.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $this->assertSame(PreviewKind::None, $attachment->previewKind());
        $this->assertFalse($attachment->isPreviewable());

        $this->pageFor($attachment)
            ->assertOk()->assertSee('no preview for .docx')->assertDontSeeHtml('data-tm-preview="0"');
    }

    #[Test]
    public function images_get_a_thumbnail_in_the_attachment_row(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        $attachment = $this->attach($png, 'pixel.png', 'image/png');

        $this->pageFor($attachment)->assertOk()->assertSeeHtml('class="tm-thumb"')->assertSeeHtml('loading="lazy"');
    }

    #[Test]
    public function lightbox_indices_stay_contiguous_when_a_file_cannot_be_previewed(): void
    {
        // A non-previewable file in the middle must not leave a gap in the
        // prev/next sequence.
        Mail::to('rachel@example.test')->send(
            (new OrderShipped)
                ->attachData('one', 'a.txt', ['mime' => 'text/plain'])
                ->attachData('PK zip', 'b.zip', ['mime' => 'application/zip'])
                ->attachData('three', 'c.txt', ['mime' => 'text/plain'])
        );

        $message = TestMailMessage::query()->latest('id')->first();

        $response = $this->get('/test-mail/'.$message->id)->assertOk();

        $response->assertSeeHtml('data-tm-preview="0"');
        $response->assertSeeHtml('data-tm-preview="1"');
        $response->assertDontSeeHtml('data-tm-preview="2"');
        $response->assertSee('no preview for .zip');
    }

    #[Test]
    public function embedded_images_alone_still_get_an_attachments_tab(): void
    {
        // The inline count lives inside the Attachments pane, so gating that
        // pane on file attachments alone made it unreachable for a message
        // whose only parts are embedded images.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        Mail::send([], [], function ($message) use ($png): void {
            $cid = $message->embedData($png, 'logo.png', 'image/png');

            $message->to('rachel@example.test')
                ->subject('Embedded only')
                ->html('<p><img src="'.$cid.'"></p>');
        });

        $target = TestMailMessage::query()->latest('id')->first();

        $this->assertCount(0, $target->load('attachments')->fileAttachments());
        $this->assertCount(1, $target->inlineAttachments());

        $this->get('/test-mail/'.$target->id)
            ->assertOk()
            ->assertSeeHtml('data-tm-tab="files"')
            ->assertSee('inline image')
            ->assertSee('no files are attached');
    }

    #[Test]
    public function the_list_snippet_does_not_run_block_elements_together(): void
    {
        Mail::html(
            '<h2>Order shipped</h2><p>It left the warehouse today.</p>',
            fn ($message) => $message->to('rachel@example.test')->subject('Snippet')
        );

        $snippet = TestMailMessage::query()->latest('id')->first()->preview();

        $this->assertSame('Order shipped It left the warehouse today.', $snippet);
        $this->assertStringNotContainsString('shippedIt', $snippet);
    }

    #[Test]
    public function the_poll_baseline_is_the_global_newest_id_not_the_pages_newest(): void
    {
        // Otherwise opening page two, or filtering out the newest message,
        // immediately claims new mail has arrived.
        config()->set('test-mail.ui.per_page', 2);

        foreach (range(1, 5) as $n) {
            Mail::to('rachel@example.test')->send(new OrderShipped('A-'.$n));
        }

        $newest = (int) TestMailMessage::query()->max('id');

        $this->get('/test-mail?page=2')
            ->assertOk()
            ->assertSeeHtml('var known = '.$newest.';');
    }

    #[Test]
    public function no_lightbox_is_rendered_when_a_message_has_no_attachments(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped);

        $message = TestMailMessage::query()->latest('id')->first();

        $this->get('/test-mail/'.$message->id)->assertOk()->assertDontSeeHtml('id="tm-lightbox"');
    }
}
