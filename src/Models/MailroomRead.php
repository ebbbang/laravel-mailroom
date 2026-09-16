<?php

namespace Ebbbang\Mailroom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * A record that one person has read one captured message.
 *
 * Read state is per reader rather than global, because a staging mailbox is
 * shared: what one tester has worked through says nothing about what the next
 * one still needs to look at.
 *
 * @property int $id
 * @property int $mailroom_message_id
 * @property string $reader_id
 * @property Carbon $read_at
 */
class MailroomRead extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('mailroom.database.reads_table', 'mailroom_reads');
    }

    public function getConnectionName(): ?string
    {
        return config('mailroom.database.connection');
    }

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(MailroomMessage::class, 'mailroom_message_id');
    }

    /**
     * Mark messages read for one reader.
     *
     * `insertOrIgnore` rather than a read-then-write: the unique index is what
     * makes opening a message twice a no-op, and this stays one statement
     * whether it is a single message or a whole page being marked at once.
     *
     * @param  array<int, int>  $messageIds
     */
    public static function markRead(array $messageIds, string $reader): void
    {
        if ($messageIds === []) {
            return;
        }

        $now = Date::now();

        static::query()->insertOrIgnore(array_map(fn (int $id): array => [
            'mailroom_message_id' => $id,
            'reader_id' => $reader,
            'read_at' => $now,
        ], array_values($messageIds)));
    }

    /**
     * @param  array<int, int>  $messageIds
     */
    public static function markUnread(array $messageIds, string $reader): void
    {
        if ($messageIds === []) {
            return;
        }

        static::query()
            ->where('reader_id', $reader)
            ->whereIn('mailroom_message_id', $messageIds)
            ->delete();
    }
}
