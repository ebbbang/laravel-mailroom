<?php

namespace Ebbbang\TestMail\Support;

use Ebbbang\TestMail\Models\TestMailAttachment;
use Ebbbang\TestMail\Models\TestMailMessage;

/**
 * Renders the text-shaped attachment kinds server-side.
 *
 * Nothing here streams bytes to the browser: the contents are read, parsed,
 * and handed to Blade as plain arrays and strings to be escaped. A .html or
 * .js attachment is therefore shown as source and can never execute, because
 * it is never served with a content type in the first place.
 *
 * @phpstan-type Rendered array{
 *     state: 'ok'|'too-large'|'empty'|'unreadable',
 *     kind: PreviewKind,
 *     body: string|null,
 *     rows: array<int, array<int, string>>|null,
 *     fields: array<string, string>|null,
 *     notes: array<int, string>,
 * }
 */
class TextPreview
{
    /**
     * @return Rendered
     */
    public function render(TestMailAttachment $attachment): array
    {
        $kind = $attachment->previewKind();
        $limit = (int) config('test-mail.preview.max_inline_bytes', 2 * 1024 * 1024);

        if ($attachment->size > $limit) {
            return $this->result($kind, 'too-large', notes: [
                sprintf(
                    'This file is %s, over the %s inline preview limit. Download it to read the whole thing.',
                    $attachment->humanSize(),
                    TestMailMessage::formatBytes($limit)
                ),
            ]);
        }

        $contents = $attachment->contents();

        if ($contents === null) {
            return $this->result($kind, 'unreadable');
        }

        if (trim($contents) === '') {
            return $this->result($kind, 'empty');
        }

        // Invalid UTF-8 would break the page it is escaped into, and a text
        // preview of binary noise is useless anyway.
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'UTF-8');
        }

        return match ($kind) {
            PreviewKind::Csv => $this->csv($contents, $attachment),
            PreviewKind::Json => $this->json($contents),
            PreviewKind::Calendar => $this->calendar($contents),
            PreviewKind::Message => $this->message($contents),
            default => $this->result($kind, 'ok', body: $this->normalizeNewlines($contents)),
        };
    }

    /**
     * @return Rendered
     */
    protected function csv(string $contents, TestMailAttachment $attachment): array
    {
        $delimiter = $attachment->previewKind() === PreviewKind::Csv
            && str_contains((string) $attachment->mime_type, 'tab-separated') ? "\t" : $this->sniffDelimiter($contents);

        $max = (int) config('test-mail.preview.max_csv_rows', 200);
        $notes = [];
        $rows = [];
        $total = 0;

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $total++;

            if (count($rows) < $max) {
                $rows[] = array_map(
                    fn (?string $cell): string => (string) $cell,
                    str_getcsv($line, $delimiter, '"', '\\')
                );
            }
        }

        if ($total > count($rows)) {
            $notes[] = sprintf('Showing the first %d of %d rows.', count($rows), $total);
        }

        if ($rows === []) {
            return $this->result(PreviewKind::Csv, 'empty');
        }

        return $this->result(PreviewKind::Csv, 'ok', rows: $rows, notes: $notes);
    }

    /**
     * @return Rendered
     */
    protected function json(string $contents): array
    {
        $decoded = json_decode($contents, true);

        // Malformed JSON in a test email is a finding, not something to paper
        // over: show the raw text and say why it was not formatted.
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->result(PreviewKind::Json, 'ok',
                body: $this->normalizeNewlines($contents),
                notes: ['Not valid JSON ('.json_last_error_msg().') — showing the raw text.'],
            );
        }

        $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->result(PreviewKind::Json, 'ok',
            body: $encoded === false ? $this->normalizeNewlines($contents) : $encoded,
        );
    }

    /**
     * Pull the interesting fields out of the first VEVENT, keeping the source
     * underneath so nothing is hidden.
     *
     * @return Rendered
     */
    protected function calendar(string $contents): array
    {
        $unfolded = preg_replace('/\r\n[ \t]/', '', $contents) ?? $contents;
        $fields = [];

        $wanted = [
            'SUMMARY' => 'Summary',
            'DTSTART' => 'Starts',
            'DTEND' => 'Ends',
            'LOCATION' => 'Location',
            'ORGANIZER' => 'Organizer',
            'STATUS' => 'Status',
            'DESCRIPTION' => 'Description',
        ];

        foreach (preg_split('/\r\n|\r|\n/', $unfolded) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);

            // Strip parameters, e.g. DTSTART;TZID=Europe/London.
            $name = strtoupper(explode(';', $name, 2)[0]);

            if (isset($wanted[$name]) && ! isset($fields[$wanted[$name]]) && trim($value) !== '') {
                $fields[$wanted[$name]] = $this->unescapeCalendarText(trim($value));
            }
        }

        return $this->result(PreviewKind::Calendar, 'ok',
            body: $this->normalizeNewlines($contents),
            fields: $fields === [] ? null : $fields,
            notes: $fields === [] ? ['No VEVENT fields found — showing the raw source.'] : [],
        );
    }

    /**
     * An attached .eml. We have no MIME parser to hand -- Symfony Mime builds
     * messages rather than reading them -- so split the headers off the body
     * and surface the ones that matter.
     *
     * @return Rendered
     */
    protected function message(string $contents): array
    {
        $normalized = $this->normalizeNewlines($contents);
        $split = preg_split("/\n\n/", $normalized, 2);

        $headerBlock = $split[0] ?? '';
        $body = $split[1] ?? '';

        // Unfold continuation lines before parsing.
        $headerBlock = preg_replace('/\n[ \t]+/', ' ', $headerBlock) ?? $headerBlock;

        $wanted = ['from' => 'From', 'to' => 'To', 'cc' => 'Cc', 'subject' => 'Subject', 'date' => 'Date'];
        $fields = [];

        foreach (explode("\n", $headerBlock) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $key = strtolower(trim($name));

            if (isset($wanted[$key]) && ! isset($fields[$wanted[$key]]) && trim($value) !== '') {
                $fields[$wanted[$key]] = trim($value);
            }
        }

        return $this->result(PreviewKind::Message, 'ok',
            body: trim($body) === '' ? $normalized : $body,
            fields: $fields === [] ? null : $fields,
            notes: $fields === []
                ? ['No recognisable headers — showing the raw source.']
                : ['Headers and the top-level body only; nested parts are not expanded.'],
        );
    }

    /**
     * Pick between comma, semicolon and tab by counting them in the first line.
     */
    protected function sniffDelimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\r\n");

        if ($firstLine === false) {
            return ',';
        }

        $counts = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];

        arsort($counts);

        $best = array_key_first($counts);

        return $counts[$best] > 0 ? (string) $best : ',';
    }

    protected function unescapeCalendarText(string $value): string
    {
        return str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $value);
    }

    protected function normalizeNewlines(string $contents): string
    {
        return preg_replace('/\r\n|\r/', "\n", $contents) ?? $contents;
    }

    /**
     * @param  array<int, array<int, string>>|null  $rows
     * @param  array<string, string>|null  $fields
     * @param  array<int, string>  $notes
     * @return Rendered
     */
    protected function result(
        PreviewKind $kind,
        string $state,
        ?string $body = null,
        ?array $rows = null,
        ?array $fields = null,
        array $notes = [],
    ): array {
        return [
            'state' => $state,
            'kind' => $kind,
            'body' => $body,
            'rows' => $rows,
            'fields' => $fields,
            'notes' => $notes,
        ];
    }
}
