<?php

namespace Ebbbang\Mailroom\Tests\Unit;

use Ebbbang\Mailroom\Support\Highlight;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HighlightTest extends TestCase
{
    #[Test]
    public function it_marks_every_match_ignoring_case(): void
    {
        $this->assertSame(
            'Your <mark>refund</mark> and a <mark>Refund</mark> note',
            Highlight::mark('Your refund and a Refund note', 'REFUND')->toHtml(),
        );
    }

    #[Test]
    public function the_text_is_escaped_whether_or_not_it_matches(): void
    {
        $this->assertSame(
            '&lt;b&gt;<mark>x</mark>&lt;/b&gt;',
            Highlight::mark('<b>x</b>', 'x')->toHtml(),
        );

        $this->assertSame('&lt;script&gt;', Highlight::mark('<script>', 'zzz')->toHtml());
    }

    #[Test]
    public function the_term_is_escaped_too_and_never_read_as_a_pattern(): void
    {
        $this->assertSame('a<mark>.*</mark>b', Highlight::mark('a.*b', '.*')->toHtml());
        $this->assertSame('<mark>&lt;b&gt;</mark>', Highlight::mark('<b>', '<b>')->toHtml());
    }

    #[Test]
    public function multibyte_text_is_matched_by_character(): void
    {
        $this->assertSame('Jürgen <mark>Müller</mark>', Highlight::mark('Jürgen Müller', 'müller')->toHtml());
    }

    #[Test]
    public function a_blank_term_or_text_is_just_escaped(): void
    {
        $this->assertSame('a &amp; b', Highlight::mark('a & b', '  ')->toHtml());
        $this->assertSame('', Highlight::mark(null, 'x')->toHtml());
    }
}
