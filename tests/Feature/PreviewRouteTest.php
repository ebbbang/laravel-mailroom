<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Models\MailroomAttachment;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Support\PreviewKind;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class PreviewRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewMailroom', fn (?User $user): bool => true);
    }

    /** A genuine 1x1 PNG. */
    protected function png(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
    }

    protected function attach(string $contents, string $name, string $mime): MailroomAttachment
    {
        Mail::to('rachel@example.test')->send(
            (new OrderShipped)->attachData($contents, $name, ['mime' => $mime])
        );

        return MailroomMessage::query()->latest('id')->first()->attachments()->sole();
    }

    protected function previewUrl(MailroomAttachment $attachment): string
    {
        return sprintf(
            '/mailroom/%d/attachments/%d/preview',
            $attachment->mailroom_message_id,
            $attachment->id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Headers
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_serves_an_image_with_its_real_type_under_a_sandbox_policy(): void
    {
        $attachment = $this->attach($this->png(), 'pixel.png', 'image/png');

        $response = $this->get($this->previewUrl($attachment))->assertOk();

        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Resource-Policy'));
        $this->assertSame('bytes', $response->headers->get('Accept-Ranges'));
        $this->assertSame($this->png(), $response->streamedContent());
    }

    #[Test]
    public function the_policy_blocks_scripts_and_denies_the_origin(): void
    {
        $attachment = $this->attach('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'x.svg', 'image/svg+xml');

        $csp = (string) $this->get($this->previewUrl($attachment))->assertOk()
            ->headers->get('Content-Security-Policy');

        // Without allow-scripts an SVG cannot execute even when the URL is
        // opened directly, and without allow-same-origin it reaches no cookies.
        $this->assertStringContainsString('sandbox', $csp);
        $this->assertStringNotContainsString('allow-scripts', $csp);
        $this->assertStringNotContainsString('allow-same-origin', $csp);
        $this->assertStringContainsString("default-src 'none'", $csp);
    }

    #[Test]
    public function pdf_is_deliberately_exempt_from_the_sandbox_policy(): void
    {
        // Chrome renders PDFs through a viewer extension in a cross-process
        // iframe, and a sandbox on the response blocks that extension's own
        // scripts, so the PDF does not render at all. See crbug.com/413851.
        // The remaining protections still apply.
        $attachment = $this->attach('%PDF-1.4 fake', 'invoice.pdf', 'application/pdf');

        $response = $this->get($this->previewUrl($attachment))->assertOk();

        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Resource-Policy'));
    }

    #[Test]
    public function every_kind_other_than_pdf_keeps_the_sandbox_policy(): void
    {
        $cases = [
            ['pixel.png', 'image/png'],
            ['logo.svg', 'image/svg+xml'],
            ['tone.wav', 'audio/wav'],
            ['clip.mp4', 'video/mp4'],
        ];

        foreach ($cases as [$name, $mime]) {
            $attachment = $this->attach('payload', $name, $mime);

            $csp = (string) $this->get($this->previewUrl($attachment))->assertOk()
                ->headers->get('Content-Security-Policy');

            $this->assertStringContainsString('sandbox', $csp, $mime.' must be sandboxed');
            $this->assertStringNotContainsString('allow-scripts', $csp, $mime.' must not allow scripts');
        }
    }

    #[Test]
    public function the_served_type_comes_from_the_allowlist_not_the_claimed_type(): void
    {
        // Whoever composed the mail controls mime_type, so it is untrusted. It
        // can only ever select a known-safe entry.
        $attachment = $this->attach($this->png(), 'pixel.bin', 'image/svg+xml');

        $response = $this->get($this->previewUrl($attachment))->assertOk();

        $this->assertSame('image/svg+xml', $response->headers->get('Content-Type'));

        // And a type outside the allowlist gets no preview route at all,
        // whatever it claims about itself.
        $refused = $this->attach('MZ binary', 'thing.exe', 'application/x-msdownload');

        $this->assertSame(PreviewKind::None, $refused->previewKind());
        $this->get($this->previewUrl($refused))->assertNotFound();
    }

    #[Test]
    public function an_html_attachment_is_never_served_as_html(): void
    {
        $attachment = $this->attach('<script>alert(document.cookie)</script>', 'evil.html', 'text/html');

        $this->assertSame(PreviewKind::Code, $attachment->previewKind());

        // No byte route exists for textual kinds, so there is nothing to
        // mis-serve; the source is escaped into the page instead.
        $this->get($this->previewUrl($attachment))->assertNotFound();
    }

    #[Test]
    public function the_download_route_is_still_hardened(): void
    {
        // Regression guard. Previews must never have loosened this.
        $attachment = $this->attach($this->png(), 'pixel.png', 'image/png');

        $response = $this->get(sprintf(
            '/mailroom/%d/attachments/%d',
            $attachment->mailroom_message_id,
            $attachment->id
        ))->assertOk();

        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
    }

    /*
    |--------------------------------------------------------------------------
    | Range requests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_answers_a_range_request_with_the_exact_slice(): void
    {
        $body = str_repeat('abcdefghij', 20); // 200 bytes
        $attachment = $this->attach($body, 'tone.wav', 'audio/wav');

        $response = $this->withHeaders(['Range' => 'bytes=10-19'])
            ->get($this->previewUrl($attachment))
            ->assertStatus(206);

        $this->assertSame('bytes 10-19/200', $response->headers->get('Content-Range'));
        $this->assertSame('10', $response->headers->get('Content-Length'));
        $this->assertSame(substr($body, 10, 10), $response->streamedContent());
    }

    #[Test]
    public function an_open_ended_range_runs_to_the_end_of_the_file(): void
    {
        $body = str_repeat('x', 50).'TAIL';
        $attachment = $this->attach($body, 'tone.wav', 'audio/wav');

        $response = $this->withHeaders(['Range' => 'bytes=50-'])
            ->get($this->previewUrl($attachment))
            ->assertStatus(206);

        $this->assertSame('bytes 50-53/54', $response->headers->get('Content-Range'));
        $this->assertSame('TAIL', $response->streamedContent());
    }

    #[Test]
    public function a_suffix_range_returns_the_final_bytes(): void
    {
        $attachment = $this->attach('0123456789', 'tone.wav', 'audio/wav');

        $response = $this->withHeaders(['Range' => 'bytes=-3'])
            ->get($this->previewUrl($attachment))
            ->assertStatus(206);

        $this->assertSame('bytes 7-9/10', $response->headers->get('Content-Range'));
        $this->assertSame('789', $response->streamedContent());
    }

    #[Test]
    public function an_unsatisfiable_range_is_refused_rather_than_silently_ignored(): void
    {
        $attachment = $this->attach('0123456789', 'tone.wav', 'audio/wav');

        $response = $this->withHeaders(['Range' => 'bytes=500-600'])
            ->get($this->previewUrl($attachment))
            ->assertStatus(416);

        $this->assertSame('bytes */10', $response->headers->get('Content-Range'));
    }

    #[Test]
    public function a_range_header_we_do_not_understand_yields_the_whole_body(): void
    {
        $attachment = $this->attach('0123456789', 'tone.wav', 'audio/wav');

        $response = $this->withHeaders(['Range' => 'items=1-2'])
            ->get($this->previewUrl($attachment))
            ->assertOk();

        $this->assertSame('0123456789', $response->streamedContent());
    }

    /*
    |--------------------------------------------------------------------------
    | Availability
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function a_vanished_blob_is_not_previewable(): void
    {
        $attachment = $this->attach($this->png(), 'pixel.png', 'image/png');

        Storage::disk('local')->delete($attachment->path);

        $this->get($this->previewUrl($attachment->fresh()))->assertNotFound();
    }

    #[Test]
    public function a_skipped_blob_is_not_previewable(): void
    {
        config()->set('mailroom.storage.max_attachment_size', 1);

        $attachment = $this->attach($this->png(), 'pixel.png', 'image/png');

        $this->assertTrue($attachment->wasSkipped());
        $this->get($this->previewUrl($attachment))->assertNotFound();
    }

    #[Test]
    public function an_attachment_cannot_be_previewed_through_a_different_message(): void
    {
        $attachment = $this->attach($this->png(), 'pixel.png', 'image/png');

        Mail::to('rachel@example.test')->send(new OrderShipped('A-2'));
        $other = MailroomMessage::query()->latest('id')->first();

        $this->get(sprintf('/mailroom/%d/attachments/%d/preview', $other->id, $attachment->id))
            ->assertNotFound();
    }

    #[Test]
    public function the_preview_route_honours_the_same_gate_as_the_mailbox(): void
    {
        $attachment = $this->attach($this->png(), 'pixel.png', 'image/png');

        Gate::define('viewMailroom', fn (?User $user): bool => false);

        $this->get($this->previewUrl($attachment))->assertForbidden();
    }

    #[Test]
    public function disabling_previews_unregisters_the_route(): void
    {
        $attachment = $this->attach($this->png(), 'pixel.png', 'image/png');
        $url = $this->previewUrl($attachment);

        config()->set('mailroom.preview.enabled', false);

        $this->assertFalse($attachment->fresh()->isPreviewable());

        // The route is registered at boot, so it still resolves in-process, but
        // the controller refuses because the feature is off.
        $this->get($url)->assertNotFound();
    }
}
