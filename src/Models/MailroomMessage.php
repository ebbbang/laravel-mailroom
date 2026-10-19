<?php

namespace Ebbbang\Mailroom\Models;

use Ebbbang\Mailroom\Storage\RawMessageStore;
use Ebbbang\Mailroom\Support\AddressRole;
use Ebbbang\Mailroom\Support\Like;
use Ebbbang\Mailroom\Support\Mailbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $mailer
 * @property string|null $subject
 * @property string|null $message_id
 * @property string|null $html_body
 * @property string|null $text_body
 * @property array $headers
 * @property array $tags
 * @property array $metadata
 * @property string|null $raw_path
 * @property int $size
 * @property int $attachment_count
 * @property Carbon|null $sent_at
 * @property float|null $spam_score
 * @property array|null $spam_rules
 * @property Carbon|null $spam_checked_at
 * @property \Illuminate\Database\Eloquent\Collection<int, MailroomAttachment> $attachments
 * @property \Illuminate\Database\Eloquent\Collection<int, MailroomRead> $reads
 * @property AddressCollection $addresses
 */
class MailroomMessage extends Model
{
    /**
     * Deliberately Prunable rather than MassPrunable: mass pruning bypasses
     * model events, which would leave every raw .eml and attachment blob
     * orphaned on disk. Routing all deletes through model events means the
     * UI, the prune command and a plain delete() all clean up identically.
     */
    use Prunable;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('mailroom.database.messages_table', 'mailroom_messages');
    }

    public function getConnectionName(): ?string
    {
        return config('mailroom.database.connection');
    }

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'size' => 'integer',
            'attachment_count' => 'integer',
            'sent_at' => 'datetime',
            'spam_score' => 'float',
            'spam_rules' => 'array',
            'spam_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $message): void {
            // Explicit rather than relying on the database cascade, so this
            // still holds if the consumer publishes the migration and drops
            // the foreign key, or uses a driver that ignores it.
            $message->attachments()->delete();
            $message->reads()->delete();
            $message->addresses()->delete();

            resolve(RawMessageStore::class)->deleteMessage($message->uuid);
        });
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailroomAttachment::class, 'mailroom_message_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MailroomRead::class, 'mailroom_message_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(MailroomAddress::class, 'mailroom_message_id')
            ->orderBy('role')
            ->orderBy('position');
    }

    public function prunable(): Builder
    {
        return static::query()->where(
            'created_at', '<=', now()->subDays((int) config('mailroom.prune.retention_days', 7))
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Queries
    |--------------------------------------------------------------------------
    */

    protected function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            Like::contains($query, 'subject', $term);
            Like::contains($query, 'text_body', $term, 'or');

            // Only the roles a person would think of as the message's
            // parties: nobody searching for Rachel means "Rachel was on the
            // SMTP envelope".
            $query->orWhereHas('addresses', fn (Builder $addresses): Builder => $addresses
                ->whereIn('role', AddressRole::people())
                ->matching($term));
        });
    }

    /**
     * The messages a mailbox holds. Unread needs a reader; Mailbox::resolve()
     * never picks it without one.
     */
    protected function scopeInBox(Builder $query, Mailbox $box, ?string $reader): Builder
    {
        return match ($box) {
            Mailbox::Unread => $query->whereDoesntHave('reads', fn (Builder $reads): Builder => $reads->where('reader_id', $reader)),
            Mailbox::SpamChecked => $query->whereNotNull('spam_checked_at'),
            Mailbox::Inbox => $query,
        };
    }

    /**
     * A mailbox's order. Spam checked sorts by score, worst first, which is
     * the reason it exists: the message worth looking at is the one at the
     * top. Ties, and every other mailbox, run newest first.
     */
    protected function scopeOrderedFor(Builder $query, Mailbox $box): Builder
    {
        return $query
            ->when($box === Mailbox::SpamChecked, fn (Builder $query): Builder => $query->orderByDesc('spam_score'))
            ->orderByDesc('id');
    }

    protected function scopeForMailer(Builder $query, ?string $mailer): Builder
    {
        return blank($mailer) ? $query : $query->where('mailer', $mailer);
    }

    /**
     * Messages an address sent, received, or was copied on.
     */
    protected function scopeInvolving(Builder $query, ?string $address): Builder
    {
        if (blank($address)) {
            return $query;
        }

        return $query->whereHas('addresses', fn (Builder $addresses): Builder => $addresses
            ->whereIn('role', AddressRole::people())
            ->where('address', mb_strtolower(trim($address))));
    }

    /*
    |--------------------------------------------------------------------------
    | Addresses
    |--------------------------------------------------------------------------
    */

    public function addressesFor(AddressRole ...$roles): AddressCollection
    {
        return $this->addresses->ofRole(...$roles);
    }

    public function sender(): ?MailroomAddress
    {
        return $this->addressesFor(AddressRole::From)->first();
    }

    /**
     * The bounce address the transport was given, which a caller can set
     * independently of From.
     */
    public function envelopeSender(): ?MailroomAddress
    {
        return $this->addressesFor(AddressRole::EnvelopeFrom)->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    /** @return Collection<int, MailroomAttachment> */
    public function fileAttachments(): Collection
    {
        return $this->attachments->reject->isInline()->values();
    }

    /** @return Collection<int, MailroomAttachment> */
    public function inlineAttachments(): Collection
    {
        return $this->attachments->filter->isInline()->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Spam check
    |--------------------------------------------------------------------------
    */

    /**
     * Whether this message has been scored. The timestamp answers it rather
     * than the score, since a clean message legitimately scores zero.
     */
    public function hasSpamResult(): bool
    {
        return $this->spam_checked_at !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Raw MIME
    |--------------------------------------------------------------------------
    */

    public function hasRaw(): bool
    {
        return $this->raw_path !== null && resolve(RawMessageStore::class)->exists($this->raw_path);
    }

    /**
     * The raw MIME was stored, but the file is no longer on the disk.
     *
     * Usually a non-persistent disk: on Laravel Cloud, and anywhere else
     * running ephemeral or per-replica storage, the database row outlives
     * the blob it points at. Worth saying out loud rather than quietly
     * hiding the download button.
     */
    public function rawIsMissing(): bool
    {
        return $this->raw_path !== null && ! resolve(RawMessageStore::class)->exists($this->raw_path);
    }

    /**
     * Whether any blob belonging to this message has gone missing.
     */
    public function hasMissingFiles(): bool
    {
        if ($this->rawIsMissing()) {
            return true;
        }

        return $this->attachments->contains->isMissing();
    }

    public function raw(): ?string
    {
        return $this->raw_path === null ? null : resolve(RawMessageStore::class)->get($this->raw_path);
    }

    /**
     * @return resource|null
     */
    public function rawStream()
    {
        return $this->raw_path === null ? null : resolve(RawMessageStore::class)->readStream($this->raw_path);
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    public function displaySubject(): string
    {
        return blank($this->subject) ? '(no subject)' : $this->subject;
    }

    /**
     * A one-line snippet for the message list, preferring the text body and
     * falling back to a tag-stripped version of the HTML.
     */
    public function preview(int $limit = 120): string
    {
        $source = filled($this->text_body)
            ? $this->text_body
            : $this->htmlAsText();

        return Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $source) ?? ''), $limit);
    }

    /**
     * The HTML body reduced to the prose inside it.
     */
    protected function htmlAsText(): string
    {
        /*
         * Style and script elements go first, contents and all. Removing the
         * tags alone leaves their bodies behind as text, so a message carrying
         * a <style> block -- which most templated mail does -- would read as a
         * line of CSS rather than as anything anyone wrote.
         */
        $html = preg_replace('#<(style|script)\b[^>]*+>.*?</\1>#is', ' ', (string) $this->html_body);

        /*
         * What remains has its tags turned into spaces rather than removed
         * outright. strip_tags() on "<h2>Order shipped</h2><p>It left today</p>"
         * yields "Order shippedIt left today", running the heading into the
         * body -- the caller's whitespace collapse then tidies up the spares.
         */
        return (string) preg_replace('/<[^>]*+>/', ' ', (string) $html);
    }

    public function humanSize(): string
    {
        return static::formatBytes($this->size);
    }

    /**
     * Whether the transport was asked to deliver somewhere other than the
     * addresses on the message.
     *
     * Laravel's own helpers keep the two in step -- Mail::alwaysTo() rewrites
     * the To header rather than only redirecting the envelope -- so this only
     * fires when a caller supplies an envelope of its own. The UI surfaces it
     * because in that case the visible recipients are not the real ones.
     */
    public function envelopeDivergesFromHeaders(): bool
    {
        $headerAddresses = $this->addressesFor(...AddressRole::headerRecipients())->emails();
        $envelopeAddresses = $this->addressesFor(AddressRole::EnvelopeTo)->emails();

        sort($headerAddresses);
        sort($envelopeAddresses);

        return $headerAddresses !== $envelopeAddresses;
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, $value >= 10 ? 0 : 1).' '.$units[$unit];
    }
}
