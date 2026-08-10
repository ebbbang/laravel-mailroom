<?php

namespace Ebbbang\Mailroom\Tests\Unit;

use Ebbbang\Mailroom\Forwarding\RawHeaderRewriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Editing MIME in place is the one part of forwarding that can corrupt a
 * message rather than merely misdeliver it, so it is tested on its own,
 * without a framework in the way.
 *
 * Assertions parse the result into fields rather than searching for
 * substrings: "To: rachel@example.test" is a substring of
 * "X-Mailroom-Original-To: rachel@example.test", so substring checks quietly
 * pass and fail for the wrong reasons.
 */
class RawHeaderRewriterTest extends TestCase
{
    protected RawHeaderRewriter $rewriter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rewriter = new RawHeaderRewriter;
    }

    /**
     * Parse the header block into [name, value] pairs, unfolding as it goes.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function fields(string $raw): array
    {
        $headers = preg_match('/\R\R/', $raw, $matches, PREG_OFFSET_CAPTURE) === 1
            ? substr($raw, 0, $matches[0][1])
            : rtrim($raw, "\r\n");

        $fields = [];

        foreach (preg_split('/\R/', $headers) ?: [] as $line) {
            if ($fields !== [] && $line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
                $fields[array_key_last($fields)][1] .= ' '.trim($line);

                continue;
            }

            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $fields[] = [strtolower(trim($name)), ltrim($value, ' ')];
        }

        return $fields;
    }

    protected function field(string $raw, string $name): ?string
    {
        foreach ($this->fields($raw) as [$found, $value]) {
            if ($found === strtolower($name)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function names(string $raw): array
    {
        return array_column($this->fields($raw), 0);
    }

    protected function body(string $raw): string
    {
        preg_match('/\R\R/', $raw, $matches, PREG_OFFSET_CAPTURE);

        return substr($raw, $matches[0][1] + strlen($matches[0][0]));
    }

    /**
     * Every field must have a real name. An orphaned continuation line -- the
     * failure mode when a folded header is only partly removed -- shows up
     * here as a nameless or whitespace-led field.
     */
    protected function assertHeaderBlockIsWellFormed(string $raw): void
    {
        foreach ($this->names($raw) as $name) {
            $this->assertMatchesRegularExpression(
                '/^[a-z0-9!#$%&\'*+\-.^_`|~]+$/',
                $name,
                'Header block contains a field that is not a valid name: '.var_export($name, true)
            );
        }
    }

    #[Test]
    public function it_replaces_the_recipient_and_keeps_the_original(): void
    {
        $raw = "From: app@example.test\r\nTo: rachel@example.test\r\nSubject: Hello\r\n\r\nBody text.\r\n";

        $result = $this->rewriter->redirect($raw, 'qa@team.test');

        $this->assertSame('qa@team.test', $this->field($result, 'to'));
        $this->assertSame('rachel@example.test', $this->field($result, 'x-mailroom-original-to'));
        $this->assertSame('app@example.test', $this->field($result, 'from'));
        $this->assertSame('Hello', $this->field($result, 'subject'));
        $this->assertHeaderBlockIsWellFormed($result);
    }

    #[Test]
    public function it_drops_cc_so_those_people_are_not_implied_to_have_received_it(): void
    {
        $raw = "To: rachel@example.test\r\nCc: ops@example.test\r\nSubject: Hello\r\n\r\nBody.\r\n";

        $result = $this->rewriter->redirect($raw, 'qa@team.test');

        $this->assertNotContains('cc', $this->names($result));
        $this->assertSame('ops@example.test', $this->field($result, 'x-mailroom-original-cc'));
    }

    #[Test]
    public function it_leaves_the_body_byte_identical(): void
    {
        $body = "Dear Rachel,\r\n\r\nTo: this line looks like a header but is not.\r\nCc: neither is this.\r\n\r\n-- \r\nThe team\r\n";
        $raw = "From: app@example.test\r\nTo: rachel@example.test\r\n\r\n".$body;

        $result = $this->rewriter->redirect($raw, 'qa@team.test');

        $this->assertSame($body, $this->body($result));
    }

    #[Test]
    public function it_keeps_a_folded_recipient_list_together(): void
    {
        $raw = "From: app@example.test\r\n"
            ."To: rachel@example.test,\r\n\tdara@example.test,\r\n\tsam@example.test\r\n"
            ."Subject: Hello\r\n\r\nBody.\r\n";

        $result = $this->rewriter->redirect($raw, 'qa@team.test');

        $this->assertSame('qa@team.test', $this->field($result, 'to'));

        // All three addresses travel together onto the archived header.
        $this->assertSame(
            'rachel@example.test, dara@example.test, sam@example.test',
            $this->field($result, 'x-mailroom-original-to')
        );

        // And nothing was left stranded where the folded field used to be.
        $this->assertHeaderBlockIsWellFormed($result);
        $this->assertSame('Hello', $this->field($result, 'subject'));
    }

    #[Test]
    public function it_keeps_a_folded_field_it_does_not_touch_byte_identical(): void
    {
        $raw = "To: rachel@example.test\r\n"
            ."References: <one@example.test>\r\n\t<two@example.test>\r\n"
            ."\r\nBody.\r\n";

        $result = $this->rewriter->redirect($raw, 'qa@team.test');

        $this->assertStringContainsString("References: <one@example.test>\r\n\t<two@example.test>", $result);
    }

    #[Test]
    public function it_preserves_lf_only_line_endings(): void
    {
        $raw = "From: app@example.test\nTo: rachel@example.test\n\nBody.\n";

        $result = $this->rewriter->redirect($raw, 'qa@team.test');

        $this->assertStringNotContainsString("\r", $result);
        $this->assertSame('qa@team.test', $this->field($result, 'to'));
        $this->assertSame("Body.\n", $this->body($result));
    }

    #[Test]
    public function it_matches_header_names_case_insensitively(): void
    {
        $raw = "TO: rachel@example.test\r\ncc: ops@example.test\r\n\r\nBody.\r\n";

        $result = $this->rewriter->redirect($raw, 'qa@team.test');

        // Exactly one recipient field, carrying the new address.
        $this->assertSame(['to'], array_values(array_filter(
            $this->names($result),
            fn (string $name): bool => $name === 'to'
        )));
        $this->assertSame('qa@team.test', $this->field($result, 'to'));
        $this->assertNotContains('cc', $this->names($result));
    }

    #[Test]
    public function it_leaves_encoded_words_alone(): void
    {
        $raw = "To: rachel@example.test\r\nSubject: =?UTF-8?B?T3JkZXIgc2hpcHBlZA==?=\r\n\r\nBody.\r\n";

        $result = $this->rewriter->redirect($raw, 'qa@team.test');

        $this->assertSame('=?UTF-8?B?T3JkZXIgc2hpcHBlZA==?=', $this->field($result, 'subject'));
    }

    #[Test]
    public function it_handles_a_message_with_no_body(): void
    {
        $raw = "From: app@example.test\r\nTo: rachel@example.test\r\n";

        $result = $this->rewriter->redirect($raw, 'qa@team.test');

        $this->assertSame('qa@team.test', $this->field($result, 'to'));
        $this->assertStringEndsWith("\r\n\r\n", $result);
    }

    #[Test]
    public function it_does_not_disturb_the_headers_that_identify_the_message(): void
    {
        $raw = "Message-ID: <abc@example.test>\r\n"
            ."Date: Mon, 4 Aug 2026 10:00:00 +0000\r\n"
            ."To: rachel@example.test\r\n"
            ."Content-Type: multipart/mixed; boundary=\"_=_swift_1234_=_\"\r\n\r\nBody.\r\n";

        $result = $this->rewriter->redirect($raw, 'qa@team.test');

        $this->assertSame('<abc@example.test>', $this->field($result, 'message-id'));
        $this->assertSame('Mon, 4 Aug 2026 10:00:00 +0000', $this->field($result, 'date'));
        $this->assertSame('multipart/mixed; boundary="_=_swift_1234_=_"', $this->field($result, 'content-type'));
    }
}
