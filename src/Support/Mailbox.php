<?php

namespace Ebbbang\Mailroom\Support;

/**
 * The sidebar's mailboxes. The value is what the `box` query parameter
 * carries.
 */
enum Mailbox: string
{
    case Inbox = 'inbox';
    case Unread = 'unread';
    case SpamChecked = 'spam-checked';

    /**
     * The mailbox a request asked for, as the one that will actually be shown.
     *
     * Unread needs someone to have read things. With nobody signed in it falls
     * back to the inbox rather than refusing, the same way the rest of read
     * state simply switches off. Anything unrecognised is the inbox too, so a
     * stale or hand-made link still lands somewhere sensible.
     */
    public static function resolve(mixed $requested, ?string $reader): self
    {
        $box = is_string($requested) ? self::tryFrom($requested) : null;

        return match (true) {
            $box === self::Unread && $reader === null, $box === null => self::Inbox,
            default => $box,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Inbox => 'Inbox',
            self::Unread => 'Unread',
            self::SpamChecked => 'Spam checked',
        };
    }

    /**
     * The `box` value for a link to this mailbox. The inbox is the default,
     * so it leaves the parameter out and keeps the URL clean.
     */
    public function queryValue(): ?string
    {
        return $this === self::Inbox ? null : $this->value;
    }
}
