<?php

namespace Nvahalik\Filer;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;

/**
 * Class MetadataRepository.
 *
 * @property string path
 */
class Metadata implements Arrayable, Jsonable
{
    public ?string $id;

    public string $path;

    public ?string $etag;

    public string $filename;

    public ?string $mimetype;

    public int $created_at;

    public int $updated_at;

    public int $timestamp;

    public string $visibility = 'private';

    public MimeTypeDetector $detector;

    public static function deserialize($array)
    {
        $timestamp = Carbon::parse($array['timestamp'] ?? $array['updated_at'] ?? $array['created_at']);

        return new static(
            $array['path'],
            $array['mimetype'] ?? 'application/octet-stream',
            $array['size'],
            $array['etag'] ?? '',
            $timestamp->format('U'),
            $array['visibility'] ?? 'private',
            BackingData::unserialize($array['backing_data'] ?? []),
            $array['id'] ?? null
        );
    }

    public static function import($array)
    {
        return new static(
            $array['path'],
            $array['mimetype'] ?? 'application/octet-stream',
            $array['size'],
            $array['etag'] ?? '',
            $array['timestamp'] ?? $array['updated_at'] ?? $array['created_at'],
            $array['visibility'] ?? 'private',
            $array['id'] ?? null
        );
    }

    /**
     * @param  resource|string  $contents
     * @return false|int
     */
    public static function getSize($contents)
    {
        if (is_resource($contents)) {
            fseek($contents, 0, SEEK_END);
            $size = ftell($contents);
            rewind($contents);
        } else {
            $size = strlen($contents);
        }

        return $size;
    }

    /**
     * @param  string  $path
     * @return Metadata
     */
    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function setTimestamp(int $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * @param  mixed|string  $filename
     * @return Metadata
     */
    public function setFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * @param  string|null  $mimetype
     * @return Metadata
     */
    public function setMimetype(string $mimetype = null): self
    {
        $this->mimetype = $mimetype;

        return $this;
    }

    /**
     * @param  string  $visibility
     * @return Metadata
     */
    public function setVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * @param  int  $size
     * @return Metadata
     */
    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @param  BackingData  $backingData
     * @return Metadata
     */
    public function setBackingData(BackingData $backingData): self
    {
        $this->backingData = $backingData;

        return $this;
    }

    public int $size;

    public BackingData $backingData;

    public function __construct(
        string $path,
        string $mimetype = 'application/octet-stream',
        int $size = 0,
        string $etag = null,
        int $timestamp = null,
        string $visibility = null,
        BackingData $backingData = null,
        string $id = null
    ) {
        $this->path = $path;
        $this->mimetype = $mimetype;
        $this->size = $size;
        $this->etag = $etag;
        $this->backingData = $backingData ?? new BackingData();
        $this->visibility = $visibility ?? 'private';
        $this->created_at = $timestamp ?? time();
        $this->updated_at = $timestamp ?? time();
        $this->timestamp = $timestamp ?? time();
        $this->id = $id;

        $this->detector = new FinfoMimeTypeDetector();
    }

    public static function generateEtag($content)
    {
        if (is_resource($content)) {
            // Use incremental hashing to generate the MD5 sum without running out of memory for big files.
            $hash = hash_init('md5');
            $location = ftell($content);
            rewind($content);
            hash_update_stream($hash, $content);
            fseek($content, $location);

            return hash_final($hash);
        }

        return md5($content);
    }

    public static function generate($path, $contents): Metadata
    {
        $mimetype = \Nvahalik\Filer\Services\MimeType::detectMimeType($path, $contents);
        $size = self::getSize($contents);
        $etag = static::generateEtag($contents);

        return new static(
            $path,
            $mimetype,
            $size,
            $etag,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'etag' => $this->etag,
            'mimetype' => $this->mimetype,
            'visibility' => $this->visibility,
            'size' => $this->size,
            'backing_data' => $this->backingData,
            'timestamp' => $this->timestamp,
        ];
    }

    public function serialize()
    {
        $data = $this->toArray();

        $data['backing_data'] = $data['backing_data']->toJson();

        return $data;
    }

    public function updateContents($contents)
    {
        $mimetype = \Nvahalik\Filer\Services\MimeType::detectMimeType($contents);
        $this->size = $this->getSize($contents);
        $this->etag = $this->generateEtag($contents);
        $this->updated_at = time();

        return $this;
    }

    public function toJson($options = 0)
    {
        // TODO: Implement toJson() method.
    }
}
