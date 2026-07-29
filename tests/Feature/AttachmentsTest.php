<?php

namespace Ebbbang\TestMail\Tests\Feature;

use Ebbbang\TestMail\Models\TestMailMessage;
use Ebbbang\TestMail\Support\CidInliner;
use Ebbbang\TestMail\Tests\Fixtures\OrderShipped;
use Ebbbang\TestMail\Tests\TestCase;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class AttachmentsTest extends TestCase
{
    /** A tiny but genuine 1x1 PNG. */
    protected function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
    }

    #[Test]
    public function it_stores_attachment_bytes_byte_for_byte(): void
    {
        $bytes = $this->pngBytes();

        Mail::to('rachel@example.test')->send(
            (new OrderShipped)->attachData($bytes, 'pixel.png', ['mime' => 'image/png'])
        );

        $captured = TestMailMessage::sole();
        $attachment = $captured->attachments()->sole();

        $this->assertSame(1, $captured->attachment_count);
        $this->assertSame('pixel.png', $attachment->filename);
        $this->assertSame('image/png', $attachment->mime_type);
        $this->assertSame('attachment', $attachment->disposition);
        $this->assertSame(strlen($bytes), $attachment->size);
        $this->assertSame($bytes, $attachment->contents(), 'Attachment bytes must round-trip unchanged.');
    }

    #[Test]
    public function it_stores_attachments_added_from_a_path(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tm').'.txt';
        file_put_contents($path, 'invoice contents');

        try {
            Mail::to('rachel@example.test')->send(
                (new OrderShipped)->attach($path, ['as' => 'invoice.txt', 'mime' => 'text/plain'])
            );
        } finally {
            @unlink($path);
        }

        $attachment = TestMailMessage::sole()->attachments()->sole();

        $this->assertSame('invoice.txt', $attachment->filename);
        $this->assertSame('invoice contents', $attachment->contents());
    }

    #[Test]
    public function it_stores_attachments_pulled_from_a_storage_disk(): void
    {
        Storage::disk('local')->put('reports/summary.txt', 'quarterly numbers');

        Mail::to('rachel@example.test')->send(
            (new OrderShipped)->attach(
                Attachment::fromStorageDisk('local', 'reports/summary.txt')->as('summary.txt')->withMime('text/plain')
            )
        );

        $attachment = TestMailMessage::sole()->attachments()->sole();

        $this->assertSame('summary.txt', $attachment->filename);
        $this->assertSame('quarterly numbers', $attachment->contents());
    }

    #[Test]
    public function it_separates_inline_embeds_from_real_attachments(): void
    {
        $bytes = $this->pngBytes();

        Mail::send([], [], function ($message) use ($bytes): void {
            $cid = $message->embedData($bytes, 'logo.png', 'image/png');

            $message->to('rachel@example.test')
                ->subject('With an embedded image')
                ->html('<p>Logo: <img src="'.$cid.'"></p>');
        });

        $captured = TestMailMessage::sole()->load('attachments');

        $this->assertCount(1, $captured->inlineAttachments());
        $this->assertCount(0, $captured->fileAttachments());

        // An embedded image belongs to the body, so it must not be counted
        // as an attachment in the message list.
        $this->assertSame(0, $captured->attachment_count);

        $embedded = $captured->inlineAttachments()->first();

        $this->assertSame('inline', $embedded->disposition);
        $this->assertNotNull($embedded->content_id);
        $this->assertSame($bytes, $embedded->contents());
    }

    #[Test]
    public function it_inlines_embedded_images_as_data_uris_for_export(): void
    {
        $bytes = $this->pngBytes();

        Mail::send([], [], function ($message) use ($bytes): void {
            $cid = $message->embedData($bytes, 'logo.png', 'image/png');

            $message->to('rachel@example.test')
                ->subject('With an embedded image')
                ->html('<p><img src="'.$cid.'"></p>');
        });

        $captured = TestMailMessage::sole()->load('attachments');

        $exported = resolve(CidInliner::class)->inline($captured, $captured->html_body);

        $this->assertStringNotContainsString('cid:', (string) $exported);
        $this->assertStringContainsString('data:image/png;base64,'.base64_encode($bytes), (string) $exported);
    }

    #[Test]
    public function it_records_metadata_but_skips_bytes_over_the_configured_limit(): void
    {
        config()->set('test-mail.storage.max_attachment_size', 8);

        Mail::to('rachel@example.test')->send(
            (new OrderShipped)->attachData(str_repeat('x', 64), 'big.txt', ['mime' => 'text/plain'])
        );

        $attachment = TestMailMessage::sole()->attachments()->sole();

        $this->assertSame(64, $attachment->size);
        $this->assertNull($attachment->path);
        $this->assertFalse($attachment->hasContents());
    }

    #[Test]
    public function it_cannot_be_tricked_into_escaping_the_storage_directory(): void
    {
        Mail::to('rachel@example.test')->send(
            (new OrderShipped)->attachData('secret', '../../../evil.txt', ['mime' => 'text/plain'])
        );

        $attachment = TestMailMessage::sole()->attachments()->sole();

        $this->assertStringNotContainsString('..', (string) $attachment->path);
        $this->assertStringStartsWith('test-mail/', (string) $attachment->path);
        $this->assertSame('secret', $attachment->contents());
    }
}
