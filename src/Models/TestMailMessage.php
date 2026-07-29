<?php

namespace Ebbbang\TestMail\Models;

use Ebbbang\TestMail\Storage\RawMessageStore;
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
 * @property array $from
 * @property array $to
 * @property array $cc
 * @property array $bcc
 * @property array $reply_to
 * @property array $envelope_recipients
 * @property string|null $envelope_sender
 * @property string|null $html_body
 * @property string|null $text_body
 * @property array $headers
 * @property array $tags
 * @property array $metadata
 * @property string|null $raw_path
 * @property int $size
 * @property int $attachment_count
 * @property Carbon|null $sent_at
 * @property \Illuminate\Database\Eloquent\Collection<int, TestMailAttachment> $attachments
 */
class TestMailMessage extends Model
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
        return config('test-mail.database.messages_table', 'test_mail_messages');
    }

    public function getConnectionName(): ?string
    {
        return config('test-mail.database.connection');
    }

    protected function casts(): array
    {
        return [
            'from' => 'array',
            'to' => 'array',
            'cc' => 'array',
            'bcc' => 'array',
            'reply_to' => 'array',
            'envelope_recipients' => 'array',
            'headers' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'size' => 'integer',
            'attachment_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $message): void {
            // Explicit rather than relying on the database cascade, so this
            // still holds if the consumer publishes the migration and drops
            // the foreign key, or uses a driver that ignores it.
            $message->attachments()->delete();

            resolve(RawMessageStore::class)->deleteMessage($message->uuid);
        });
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TestMailAttachment::class, 'test_mail_message_id');
    }

    public function prunable(): Builder
    {
        return static::query()->where(
            'created_at', '<=', now()->subDays((int) config('test-mail.prune.retention_days', 7))
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

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('subject', 'like', $like)
                ->orWhere('to', 'like', $like)
                ->orWhere('from', 'like', $like)
                ->orWhere('cc', 'like', $like)
                ->orWhere('bcc', 'like', $like)
                ->orWhere('text_body', 'like', $like);
        });
    }

    protected function scopeForMailer(Builder $query, ?string $mailer): Builder
    {
        return blank($mailer) ? $query : $query->where('mailer', $mailer);
    }

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    /** @return Collection<int, TestMailAttachment> */
    public function fileAttachments(): Collection
    {
        return $this->attachments->reject->isInline()->values();
    }

    /** @return Collection<int, TestMailAttachment> */
    public function inlineAttachments(): Collection
    {
        return $this->attachments->filter->isInline()->values();
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
            : strip_tags((string) $this->html_body);

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $source) ?? ''), $limit);
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
        $headerAddresses = $this->normalizeAddresses(
            collect([$this->to, $this->cc, $this->bcc])->filter()->flatten(1)->all()
        );

        $envelopeAddresses = $this->normalizeAddresses($this->envelope_recipients ?? []);

        return $headerAddresses !== $envelopeAddresses;
    }

    /**
     * @param  array<int, array{address?: string, name?: string|null}>  $addresses
     * @return array<int, string>
     */
    protected function normalizeAddresses(array $addresses): array
    {
        return collect($addresses)
            ->pluck('address')
            ->filter()
            ->map(fn (string $address): string => Str::lower($address))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{address?: string, name?: string|null}>|null  $addresses
     */
    public static function formatAddressList(?array $addresses): string
    {
        return collect($addresses ?? [])
            ->map(fn (array $address): string => static::formatAddress($address))
            ->filter()
            ->implode(', ');
    }

    /**
     * @param  array{address?: string, name?: string|null}  $address
     */
    public static function formatAddress(array $address): string
    {
        $email = $address['address'] ?? '';
        $name = $address['name'] ?? null;

        if (blank($email)) {
            return '';
        }

        return filled($name) ? sprintf('%s <%s>', $name, $email) : $email;
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
