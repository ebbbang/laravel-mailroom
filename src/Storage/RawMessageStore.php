<?php

namespace Ebbbang\Mailroom\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;

/**
 * The single seam between captured mail and the filesystem.
 *
 * Layout:
 *   {path}/{uuid}/raw.eml
 *   {path}/{uuid}/parts/{index}-{sanitized-filename}
 *
 * Keeping every blob for a message under one directory means deleting a
 * message is a single deleteDirectory() call and can never orphan a part.
 */
class RawMessageStore
{
    /**
     * Buffer raw MIME in memory up to this size before spilling to a temp
     * file, so a large message never has to be held in PHP memory whole.
     */
    protected const MEMORY_BUFFER_BYTES = 2 * 1024 * 1024;

    public function __construct(protected FilesystemManager $filesystem) {}

    /**
     * Stream a message's raw MIME to disk.
     *
     * @param  iterable<string>  $chunks  Typically Symfony's Message::toIterable()
     * @return array{0: string, 1: int} The stored path and its byte size
     */
    public function putRaw(string $uuid, iterable $chunks): array
    {
        $stream = fopen('php://temp/maxmemory:'.self::MEMORY_BUFFER_BYTES, 'w+b');
        $size = 0;

        foreach ($chunks as $chunk) {
            $size += (int) fwrite($stream, $chunk);
        }

        rewind($stream);

        $path = $this->rawPathFor($uuid);
        $this->disk()->writeStream($path, $stream);

        // Some Flysystem adapters close the handle for us.
        if (is_resource($stream)) {
            fclose($stream);
        }

        return [$path, $size];
    }

    /**
     * Persist the bytes of a single attachment or inline part.
     */
    public function putAttachment(string $uuid, int $index, ?string $filename, string $contents): string
    {
        $path = sprintf(
            '%s/parts/%d-%s',
            $this->directoryFor($uuid),
            $index,
            $this->sanitizeFilename($filename)
        );

        $this->disk()->put($path, $contents);

        return $path;
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function get(string $path): ?string
    {
        return $this->disk()->exists($path) ? $this->disk()->get($path) : null;
    }

    /**
     * @return resource|null
     */
    public function readStream(string $path)
    {
        return $this->disk()->exists($path) ? $this->disk()->readStream($path) : null;
    }

    /**
     * Remove every blob belonging to one message.
     */
    public function deleteMessage(string $uuid): void
    {
        $this->disk()->deleteDirectory($this->directoryFor($uuid));
    }

    /**
     * Remove every blob this package has ever stored.
     */
    public function flush(): void
    {
        $this->disk()->deleteDirectory($this->root());
    }

    public function directoryFor(string $uuid): string
    {
        return $this->root().'/'.$uuid;
    }

    public function rawPathFor(string $uuid): string
    {
        return $this->directoryFor($uuid).'/raw.eml';
    }

    public function diskName(): string
    {
        return (string) config('mailroom.storage.disk', 'local');
    }

    protected function root(): string
    {
        return trim((string) config('mailroom.storage.path', 'mailroom'), '/');
    }

    protected function disk(): Filesystem
    {
        return $this->filesystem->disk($this->diskName());
    }

    /**
     * Attachment filenames arrive from whoever composed the mail and are
     * never trustworthy -- an attachment named "../../.env" must not be able
     * to escape the message directory.
     */
    protected function sanitizeFilename(?string $filename): string
    {
        $name = basename(str_replace('\\', '/', (string) $filename));
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? '';
        $name = ltrim($name, '.');

        if ($name === '') {
            return 'attachment';
        }

        return Str::limit($name, 100, '');
    }
}
