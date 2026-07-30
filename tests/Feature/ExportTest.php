<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

class ExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewMailroom', fn (?User $user): bool => true);
    }

    protected function capture(): MailroomMessage
    {
        Mail::to('rachel@example.test')->send(new OrderShipped);

        return MailroomMessage::sole();
    }

    #[Test]
    public function it_downloads_the_raw_message_as_an_eml_file(): void
    {
        $message = $this->capture();

        $response = $this->get(sprintf('/mailroom/%d/download/eml', $message->id))->assertOk();

        $this->assertSame('message/rfc822', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.eml', (string) $response->headers->get('Content-Disposition'));

        $this->assertSame($message->raw(), $response->streamedContent());
    }

    #[Test]
    public function it_downloads_the_html_body_as_a_standalone_file(): void
    {
        $message = $this->capture();

        $response = $this->get(sprintf('/mailroom/%d/download/html', $message->id))->assertOk();

        // Forced as a download: rendering email HTML inline on the app's own
        // origin would be a stored-XSS sink.
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('On its way', $response->getContent());
    }

    #[Test]
    public function the_html_preview_is_served_with_a_restrictive_content_security_policy(): void
    {
        $message = $this->capture();

        $response = $this->get(sprintf('/mailroom/%d/content/html', $message->id))->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringNotContainsString('script-src', $csp);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    #[Test]
    public function attachments_are_always_served_as_opaque_downloads(): void
    {
        Mail::to('rachel@example.test')->send(
            (new OrderShipped)->attachData('<script>alert(1)</script>', 'evil.html', ['mime' => 'text/html'])
        );

        $message = MailroomMessage::sole();
        $attachment = $message->attachments()->sole();

        $response = $this->get(sprintf('/mailroom/%s/attachments/%s', $message->id, $attachment->id))->assertOk();

        // Never the attachment's own MIME type -- an emailed .html or .svg
        // handed back with its declared type would execute on this origin.
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    #[Test]
    public function an_attachment_cannot_be_fetched_through_a_different_message(): void
    {
        Mail::to('rachel@example.test')->send(
            (new OrderShipped('A-1'))->attachData('secret', 'secret.txt', ['mime' => 'text/plain'])
        );
        Mail::to('rachel@example.test')->send(new OrderShipped('A-2'));

        [$withFile, $without] = MailroomMessage::query()->orderBy('id')->get()->all();
        $attachment = $withFile->attachments()->sole();

        $this->get(sprintf('/mailroom/%s/attachments/%s', $without->id, $attachment->id))->assertNotFound();
    }

    #[Test]
    public function it_returns_the_text_body_and_raw_source(): void
    {
        $message = $this->capture();

        $this->get(sprintf('/mailroom/%d/content/text', $message->id))
            ->assertOk()
            ->assertSee('has shipped');

        $raw = $this->get(sprintf('/mailroom/%d/content/raw', $message->id))->assertOk();

        $this->assertStringContainsString('Subject: Order A-1001 shipped', $raw->streamedContent());
    }

    #[Test]
    public function unknown_export_formats_are_rejected(): void
    {
        $message = $this->capture();

        $this->get(sprintf('/mailroom/%d/download/pdf', $message->id))->assertNotFound();
        $this->get(sprintf('/mailroom/%d/content/exe', $message->id))->assertNotFound();
    }
}
