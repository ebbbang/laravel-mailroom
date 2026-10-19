<?php

namespace Ebbbang\Mailroom\Support;

use Illuminate\Support\HtmlString;

/**
 * Wraps the matches of a search term in <mark>.
 *
 * The text is captured mail, which is attacker-controlled, so every piece is
 * escaped before it is joined with the markup. The term is quoted rather than
 * read as a pattern, so a search for ".*" finds a dot and a star.
 */
final class Highlight
{
    public static function mark(?string $text, ?string $term): HtmlString
    {
        $text = (string) $text;
        $term = trim((string) $term);

        $parts = $term === ''
            ? false
            : preg_split('/('.preg_quote($term, '/').')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        // No term, or text that is not valid UTF-8: escaped, unmarked.
        if ($parts === false) {
            return new HtmlString(e($text));
        }

        $html = '';

        foreach ($parts as $index => $part) {
            // With the term captured, every odd piece is a match.
            $html .= $index % 2 === 1 ? '<mark>'.e($part).'</mark>' : e($part);
        }

        return new HtmlString($html);
    }
}
