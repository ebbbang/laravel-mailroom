<?php

namespace Ebbbang\Mailroom\Support;

use Illuminate\Support\Str;

/**
 * The initials circle beside a person in the list, the sidebar and the
 * message header.
 *
 * The tone comes from the address rather than the name, so one person keeps
 * one colour everywhere they appear, whatever name a given message gave them.
 */
final readonly class Avatar
{
    /**
     * How many --mr-tone-N pairs the stylesheet defines.
     */
    public const TONES = 8;

    public function __construct(
        public string $initials,
        public int $tone,
    ) {}

    public static function for(?string $address, ?string $name = null): self
    {
        $address = mb_strtolower(trim((string) $address));

        return new self(
            initials: self::initials($address, $name),
            tone: $address === '' ? 0 : crc32($address) % self::TONES,
        );
    }

    /**
     * Up to two letters: the first of the first two words of the name, or of
     * the address's local part split on dots, dashes and underscores. A
     * question mark when there is nothing to go on.
     */
    protected static function initials(string $address, ?string $name): string
    {
        $source = filled($name) ? $name : Str::before($address, '@');

        $words = preg_split('/[\s._\-+]+/u', trim($source), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $letters = collect($words)
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $letters === '' ? '?' : $letters;
    }
}
