<?php declare(strict_types=1);

namespace Kuria\Parser\Input;

class StreamInput extends Input
{
    /** @var resource the input stream */
    private $stream;

    /** @var int|null $length total number of bytes available, if known */
    private $streamLength;

    /** @var int initial offset of the stream */
    private $streamOffsetInitial;

    /** @var int|null */
    private $streamOffsetCurrent;

    /** @var int chunk size */
    private $chunkSize;

    /** @var int number of past chunks to cache */
    private $chunkCacheSize;

    /** @var array past chunk cache */
    private $chunkCache = [];

    /** @var bool an attempt to load a chunk has been made */
    private $seeded = false;

    /**
     * @param resource $stream
     * @param int $length total number of bytes available (from current position), if known
     */
    function __construct($stream, ?int $length, int $chunkSize, int $chunkCacheSize = 0)
    {
        $this->stream = $stream;
        $this->streamLength = $length;
        $this->chunkSize = $chunkSize;
        $this->chunkCacheSize = $chunkCacheSize;
        $this->streamOffsetInitial = ftell($stream);
        $this->streamOffsetCurrent = $this->streamOffsetInitial;
    }

    function getTotalLength(): ?int
    {
        return $this->streamLength;
    }

    function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    function getChunkCacheSize(): int
    {
        return $this->chunkCacheSize;
    }

    function getCurrentChunkCacheSize(): int
    {
        return count($this->chunkCache);
    }

    function isPositionInChunkCache(int $position): bool
    {
        $chunkOffset = $position - $position % $this->chunkSize;

        return isset($this->chunkCache["chunk_{$chunkOffset}"]);
    }

    /**
     * Modify chunk cache size
     *
     * Extra cached chunks are dropped immediately.
     */
    function setChunkCacheSize(int $chunkCacheSize): void
    {
        $this->chunkCacheSize = $chunkCacheSize;

        if (($currentChunkCacheSize = count($this->chunkCache)) > $this->chunkCacheSize) {
            array_splice($this->chunkCache, 0, $currentChunkCacheSize - $this->chunkCacheSize);
        }
    }

    function clearChunkCache(): void
    {
        $this->chunkCache = [];
    }

    function loadData(int $position): bool
    {
        // check position and available bytes
        if ($position < 0 || $this->streamLength !== null && $position >= $this->streamLength) {
            return false;
        }

        // calculate chunk offset
        $chunkOffset = $position - $position % $this->chunkSize;

        // check chunk offset
        if ($this->seeded && $this->offset === $chunkOffset) {
            // the chunk is already loaded, check position only
            $success = isset($this->data[$position - $chunkOffset]);
        } else {
            // not loaded, check cache (if enabled)
            if ($this->chunkCacheSize > 0 && isset($this->chunkCache["chunk_{$chunkOffset}"])) {
                // cache hit
                if ($this->seeded) {
                    // swap current chunk with the cached one
                    $currentChunkOffset = $this->offset;
                    $currentChunk = $this->data;
                    $currentChunkLength = $this->length;

                    $this->loadCachedChunk($chunkOffset);
                    $this->putChunkInCache($currentChunk, $currentChunkOffset, $currentChunkLength);
                } else {
                    // just load the cached chunk
                    $this->loadCachedChunk($chunkOffset);
                }

                // check position
                $success =  isset($this->data[$position - $chunkOffset]);
            } else {
                // load from stream
                if ($this->seeded && $this->chunkCacheSize > 0) {
                    // cache current chunk
                    $this->putChunkInCache($this->data, $this->offset, $this->length);
                }
                $success = $this->loadChunkFromStream($position, $chunkOffset);
            }
        }

        $this->seeded = true;

        return $success;
    }

    private function loadChunkFromStream(int $position, int $chunkOffset): bool
    {
        // seek (if needed)
        if ($this->streamOffsetCurrent !== ($seekOffset = $this->streamOffsetInitial + $chunkOffset)) {
            fseek($this->stream, $seekOffset);
            $this->streamOffsetCurrent = $seekOffset;
        }

        // determine how many bytes to read
        if ($this->streamLength !== null && $this->streamLength - $position < $this->chunkSize) {
            // near the end, do not read more than is available
            $bytesToRead = $this->streamLength - $position;
        } else {
            // not near the end or unknown available bytes
            $bytesToRead = $this->chunkSize;
        }

        // read
        $this->data = '';
        $this->offset = $chunkOffset;
        $this->length = 0;
        do {
            $this->data .= fread($this->stream, $bytesToRead);

            $bytesRead = strlen($this->data) - $this->length;
            $bytesToRead -= $bytesRead;
            $this->length += $bytesRead;
        } while ($bytesRead > 0 && $bytesToRead > 0);

        $this->streamOffsetCurrent += $this->length;

        return $this->length > 0 && ($this->streamLength === null || $bytesToRead === 0);
    }

    private function putChunkInCache(string $chunk, int $chunkOffset, int $chunkLength): void
    {
        $this->chunkCache["chunk_{$chunkOffset}"] = [$chunk, $chunkLength];

        if (count($this->chunkCache) > $this->chunkCacheSize) {
            array_shift($this->chunkCache);
        }
    }

    private function loadCachedChunk(int $chunkOffset): void
    {
        $this->offset = $chunkOffset;
        [$this->data, $this->length] = $this->chunkCache["chunk_{$chunkOffset}"];
        unset($this->chunkCache["chunk_{$chunkOffset}"]);
    }
}
