<?php

namespace Ebbbang\Mailroom\Spam;

/**
 * One SpamAssassin verdict on one captured message.
 *
 * The score is SpamAssassin's own, not ours. Nothing here weights, rescales or
 * second-guesses it, which is the only honest way to report a number computed
 * somewhere else from a rule set we do not ship.
 */
class SpamResult
{
    /**
     * @param  array<int, array{name: string, score: float, description: string}>  $rules
     */
    public function __construct(
        public readonly float $score,
        public readonly array $rules,
    ) {}

    /**
     * Build a result from Postmark's JSON.
     *
     * The rule entries are read defensively. Postmark documents the response as
     * an array of "rule objects" without pinning the key names, and a service
     * that says it "may be updated, removed or changed at any time" is not one
     * to hold to an exact shape. A rule whose name we cannot find is still
     * worth showing for its score and description.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPostmark(array $payload): self
    {
        $names = static::ruleNamesFrom((string) ($payload['report'] ?? ''));

        $rules = [];

        foreach (array_values(array_filter((array) ($payload['rules'] ?? []), is_array(...))) as $index => $rule) {
            $rules[] = [
                'name' => (string) ($rule['rule'] ?? $rule['name'] ?? $names[$index] ?? ''),
                'score' => (float) ($rule['score'] ?? 0),
                'description' => trim((string) ($rule['description'] ?? '')),
            ];
        }

        // Scores arrive as strings, which is why everything here is cast.
        return new self((float) ($payload['score'] ?? 0), $rules);
    }

    /**
     * Rule names, in order, read out of the plain text report.
     *
     * The JSON rules carry a score and a description and no name at all, while
     * the report is a fixed width table holding all three. The name is the half
     * worth keeping: "MISSING_DATE" can be looked up, where "Missing Date:
     * header" can only be read. Both lists come out of one run, so they pair by
     * position.
     *
     * Wrapped description lines carry no score, and the header and rule lines
     * start with words rather than numbers, so a leading score is what
     * distinguishes a row.
     *
     * @return array<int, string>
     */
    protected static function ruleNamesFrom(string $report): array
    {
        preg_match_all('/^\s*-?\d+\.\d+\s+(\S+)\s/m', $report, $matches);

        return $matches[1];
    }

    /**
     * SpamAssassin's conventional threshold, which is what its own default
     * configuration treats as spam. It says nothing about where a given
     * provider will actually file the message.
     */
    public function overThreshold(): bool
    {
        return $this->score >= 5.0;
    }
}
