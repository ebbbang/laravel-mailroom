<?php

namespace Ebbbang\TestMail\Models;

use Ebbbang\TestMail\Storage\RawMessageStore;
use Ebbbang\TestMail\Support\AttachmentPreview;
use Ebbbang\TestMail\Support\PreviewKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $test_mail_message_id
 * @property string|null $filename
 * @property string|null $mime_type
 * @property int $size
 * @property string $disposition
 * @property string|null $content_id
 * @property string|null $path
 */
class TestMailAttachment extends Model
{
    protected $guarded = [];

    /**
     * Memoized: resolving a preview can touch the disk to sniff an
     * unidentifiable file, and the mailbox asks for this several times per row.
     */
    protected ?AttachmentPreview $resolvedPreview = null;

    public function getTable(): string
    {
        return config('test-mail.database.attachments_table', 'test_mail_attachments');
    }

    public function getConnectionName(): ?string
    {
        return config('test-mail.database.connection');
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TestMailMessage::class, 'test_mail_message_id');
    }

    /**
     * Inline parts are the images produced by $message->embed(), referenced
     * from the HTML body via a cid: URI rather than shown as a file.
     */
    public function isInline(): bool
    {
        return $this->disposition === 'inline';
    }

    /**
     * Whether the bytes for this part are available to read.
     */
    public function hasContents(): bool
    {
        return $this->path !== null && $this->store()->exists($this->path);
    }

    /**
     * The bytes were never written, because the part exceeded
     * storage.max_attachment_size.
     */
    public function wasSkipped(): bool
    {
        return $this->path === null;
    }

    /**
     * The bytes were written but have since disappeared -- the hallmark of a
     * non-persistent disk. Distinct from wasSkipped(), because telling
     * someone their file was too large when it actually evaporated would
     * send them looking in entirely the wrong place.
     */
    public function isMissing(): bool
    {
        return $this->path !== null && ! $this->store()->exists($this->path);
    }

    public function contents(): ?string
    {
        return $this->path === null ? null : $this->store()->get($this->path);
    }

    /**
     * @return resource|null
     */
    public function readStream()
    {
        return $this->path === null ? null : $this->store()->readStream($this->path);
    }

    /*
    |--------------------------------------------------------------------------
    | Preview
    |--------------------------------------------------------------------------
    */

    public function preview(): AttachmentPreview
    {
        return $this->resolvedPreview ??= AttachmentPreview::for($this);
    }

    public function previewKind(): PreviewKind
    {
        return $this->preview()->kind;
    }

    /**
     * Whether the mailbox can show this attachment rather than only offer it
     * as a download. Needs a recognised kind, bytes on disk, and the feature
     * left enabled.
     */
    public function isPreviewable(): bool
    {
        return config('test-mail.preview.enabled', true)
            && $this->previewKind()->isPreviewable()
            && $this->hasContents();
    }

    /**
     * The extension shown in the "no preview for .docx" state.
     */
    public function extensionLabel(): string
    {
        $extension = strtolower(pathinfo((string) $this->filename, PATHINFO_EXTENSION));

        return $extension === '' ? 'this file type' : '.'.$extension;
    }

    /**
     * A data: URI for this part, used to inline embedded images into the
     * standalone .html export so it renders without the original mailbox.
     */
    public function toDataUri(): ?string
    {
        if (($contents = $this->contents()) === null) {
            return null;
        }

        return 'data:'.($this->mime_type ?: 'application/octet-stream').';base64,'.base64_encode($contents);
    }

    public function displayName(): string
    {
        return $this->filename ?: 'untitled';
    }

    public function humanSize(): string
    {
        return TestMailMessage::formatBytes($this->size);
    }

    protected function store(): RawMessageStore
    {
        return resolve(RawMessageStore::class);
    }
}
