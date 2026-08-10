<?php

namespace Ebbbang\Mailroom\Forwarding;

/**
 * Retarget a stored message's headers without touching its body.
 *
 * Forwarding replays the bytes we captured rather than rebuilding the message,
 * so when the destination changes we have to edit MIME in place. That is only
 * safe under three rules, each of which this class exists to hold:
 *
 *   1. Never look past the first blank line. A body can quite legitimately
 *      contain a line beginning "To:" -- a quoted reply, a plain-text receipt
 *      -- and rewriting that would corrupt the message.
 *   2. Treat folded headers as one field. A long recipient list wraps onto
 *      continuation lines beginning with a space or tab, and dropping only the
 *      first line of one would leave its remainder behind as a syntax error.
 *   3. Keep whatever line ending the message already uses. Mixing CRLF and LF
 *      inside a header block breaks parsers that are stricter than ours.
 */
class RawHeaderRewriter
{
    /**
     * Point a message at a single new recipient.
     *
     * The original To and Cc are preserved as X-Mailroom-Original-* so the
     * tester can still see who the message was addressed to, and Cc is dropped
     * outright: those people are not receiving this copy, and leaving the
     * header in place would suggest they were.
     */
    public function redirect(string $raw, string $address): string
    {
        [$headers, $separator, $body] = $this->split($raw);

        $eol = str_contains($headers, "\r\n") ? "\r\n" : "\n";
        $fields = $this->unfold($headers, $eol);

        $rewritten = array_values(array_filter(
            $fields,
            fn (string $field): bool => ! $this->named($field, ['to', 'cc'])
        ));

        $rewritten[] = 'To: '.$address;

        foreach (['To', 'Cc'] as $name) {
            $original = $this->valueOf($fields, strtolower($name));

            if ($original !== null) {
                $rewritten[] = 'X-Mailroom-Original-'.$name.': '.$original;
            }
        }

        return implode($eol, $rewritten).$separator.$body;
    }

    /**
     * Cut the message at its first blank line.
     *
     * \R matches CRLF as a single unit, so this finds the separator whichever
     * ending the message uses, and hands it back untouched to be reused.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    protected function split(string $raw): array
    {
        if (preg_match('/\R\R/', $raw, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            // Headers with no body at all. Terminate them properly rather than
            // handing back something a parser would reject.
            $eol = str_contains($raw, "\r\n") ? "\r\n" : "\n";

            return [rtrim($raw, "\r\n"), $eol.$eol, ''];
        }

        [$separator, $offset] = $matches[0];

        return [
            substr($raw, 0, $offset),
            $separator,
            substr($raw, $offset + strlen($separator)),
        ];
    }

    /**
     * Group the header block into one entry per field, folds included.
     *
     * Continuation lines stay attached to their field with the original line
     * ending, so any field we do not touch is reassembled byte for byte.
     *
     * @return array<int, string>
     */
    protected function unfold(string $headers, string $eol): array
    {
        $fields = [];

        foreach (preg_split('/\R/', $headers) ?: [] as $line) {
            if ($fields !== [] && $line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
                $fields[array_key_last($fields)] .= $eol.$line;

                continue;
            }

            $fields[] = $line;
        }

        return $fields;
    }

    /**
     * @param  array<int, string>  $names
     */
    protected function named(string $field, array $names): bool
    {
        foreach ($names as $name) {
            if (preg_match('/^'.preg_quote($name, '/').'[ \t]*:/i', $field) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The value of a field, with its folding left intact.
     *
     * @param  array<int, string>  $fields
     */
    protected function valueOf(array $fields, string $name): ?string
    {
        foreach ($fields as $field) {
            if (! $this->named($field, [$name])) {
                continue;
            }

            $value = substr($field, strpos($field, ':') + 1);

            // Only the single space that conventionally follows the colon is
            // removed; the folds are what make a long value legal on the new
            // header, so they stay.
            return ltrim($value, ' ');
        }

        return null;
    }
}
