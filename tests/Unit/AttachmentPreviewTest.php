<?php

namespace Ebbbang\TestMail\Tests\Unit;

use Ebbbang\TestMail\Support\AttachmentPreview;
use Ebbbang\TestMail\Support\PreviewKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Kind resolution is pure, so it needs no application.
 */
class AttachmentPreviewTest extends TestCase
{
    #[Test]
    #[DataProvider('mimeTypes')]
    public function it_resolves_a_kind_from_a_declared_mime_type(string $mime, PreviewKind $expected, ?string $served): void
    {
        $preview = AttachmentPreview::resolve($mime, null);

        $this->assertSame($expected, $preview->kind);
        $this->assertSame($served, $preview->contentType);
    }

    public static function mimeTypes(): iterable
    {
        yield 'png' => ['image/png', PreviewKind::Image, 'image/png'];
        yield 'jpeg' => ['image/jpeg', PreviewKind::Image, 'image/jpeg'];
        yield 'svg' => ['image/svg+xml', PreviewKind::Svg, 'image/svg+xml'];
        yield 'pdf' => ['application/pdf', PreviewKind::Pdf, 'application/pdf'];
        yield 'mp3' => ['audio/mpeg', PreviewKind::Audio, 'audio/mpeg'];
        yield 'mp4' => ['video/mp4', PreviewKind::Video, 'video/mp4'];
        yield 'plain text' => ['text/plain', PreviewKind::Text, null];
        yield 'csv' => ['text/csv', PreviewKind::Csv, null];
        yield 'json' => ['application/json', PreviewKind::Json, null];
        yield 'calendar' => ['text/calendar', PreviewKind::Calendar, null];
        yield 'nested message' => ['message/rfc822', PreviewKind::Message, null];

        // The formats we deliberately cannot preview.
        yield 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', PreviewKind::None, null];
        yield 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', PreviewKind::None, null];
        yield 'zip' => ['application/zip', PreviewKind::None, null];
        yield 'unknown' => ['application/x-nonsense', PreviewKind::None, null];
    }

    #[Test]
    #[DataProvider('aliases')]
    public function it_folds_mime_aliases_onto_one_canonical_type(string $alias, string $canonical): void
    {
        $this->assertSame($canonical, AttachmentPreview::resolve($alias, null)->contentType);
    }

    public static function aliases(): iterable
    {
        yield ['image/jpg', 'image/jpeg'];
        yield ['image/pjpeg', 'image/jpeg'];
        yield ['image/svg', 'image/svg+xml'];
        yield ['image/vnd.microsoft.icon', 'image/x-icon'];
        yield ['audio/mp3', 'audio/mpeg'];
        yield ['audio/x-wav', 'audio/wav'];
        yield ['audio/x-m4a', 'audio/mp4'];
        yield ['video/x-m4v', 'video/mp4'];
    }

    #[Test]
    public function it_ignores_mime_parameters_such_as_charset(): void
    {
        $preview = AttachmentPreview::resolve('text/csv; charset=utf-8', null);

        $this->assertSame(PreviewKind::Csv, $preview->kind);
    }

    #[Test]
    public function html_attachments_are_source_code_and_never_served_bytes(): void
    {
        foreach (['text/html', 'application/xhtml+xml', 'text/javascript', 'text/css'] as $mime) {
            $preview = AttachmentPreview::resolve($mime, null);

            $this->assertSame(PreviewKind::Code, $preview->kind, $mime.' must resolve to source code');
            $this->assertNull($preview->contentType, $mime.' must never be served with a content type');
            $this->assertFalse($preview->kind->servesBytes());
            $this->assertTrue($preview->kind->isTextual());
        }
    }

    #[Test]
    public function it_falls_back_to_the_filename_extension_when_the_type_is_useless(): void
    {
        $preview = AttachmentPreview::resolve('application/octet-stream', 'diagram.png');

        $this->assertSame(PreviewKind::Image, $preview->kind);
        $this->assertSame('image/png', $preview->contentType);
    }

    #[Test]
    public function a_declared_type_wins_over_the_extension(): void
    {
        // Both are recognised, so no sniffing is involved: the declared type
        // decides, and either way the served type comes from the allowlist.
        $preview = AttachmentPreview::resolve('application/pdf', 'report.png');

        $this->assertSame(PreviewKind::Pdf, $preview->kind);
        $this->assertSame('application/pdf', $preview->contentType);
    }

    #[Test]
    public function an_unrecognised_extension_yields_no_preview(): void
    {
        $this->assertSame(PreviewKind::None, AttachmentPreview::resolve(null, 'archive.7z')->kind);
        $this->assertSame(PreviewKind::None, AttachmentPreview::resolve(null, 'noextension')->kind);
        $this->assertSame(PreviewKind::None, AttachmentPreview::resolve(null, null)->kind);
    }

    #[Test]
    public function formats_browsers_render_inconsistently_are_left_out(): void
    {
        // Chrome cannot draw either, so offering a preview would show a broken
        // image on some browsers and work on others.
        $this->assertSame(PreviewKind::None, AttachmentPreview::resolve('image/tiff', 'scan.tiff')->kind);
        $this->assertSame(PreviewKind::None, AttachmentPreview::resolve('image/heic', 'photo.heic')->kind);
    }

    #[Test]
    public function kinds_report_how_they_are_rendered(): void
    {
        $this->assertTrue(PreviewKind::Image->servesBytes());
        $this->assertTrue(PreviewKind::Pdf->servesBytes());
        $this->assertFalse(PreviewKind::Csv->servesBytes());
        $this->assertFalse(PreviewKind::None->servesBytes());

        $this->assertTrue(PreviewKind::Audio->isMedia());
        $this->assertTrue(PreviewKind::Video->isMedia());
        $this->assertFalse(PreviewKind::Image->isMedia());

        $this->assertFalse(PreviewKind::None->isPreviewable());
        $this->assertTrue(PreviewKind::Text->isPreviewable());
    }
}
