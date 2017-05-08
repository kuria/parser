<?php

namespace Kuria\Parser\Input;

/**
 * Stream input
 *
 * @author ShiraNai7 <shira.cz>
 */
class StreamInput extends Input
{
    /** @var resource the input stream */
    protected $stream;
    /** @var int|null $length total number of bytes available, if known */
    protected $streamLength;
    /** @var int initial offset of the stream */
    protected $streamOffsetInitial;
    /** @var int|null */
    protected $streamOffsetCurrent;
    /** @var int chunk size */
    protected $chunkSize;
    /** @var int number of past chunks to cache */
    protected $chunkCacheSize;
    /** @var array past chunk cache */
    protected $chunkCache = array();
    /** @var bool an attempt to load a chunk has been made 1/0 */
    protected $seeded = false;

    /**
     * @param resource $stream         the input stream
     * @param int|null $length         total number of bytes available (from current position), if known
     * @param int      $chunkSize      length of single loaded chunk in bytes
     * @param int      $chunkCacheSize number of past chunks to cache for reuse (0 to disable)
     */
    public function __construct($stream, $length, $chunkSize, $chunkCacheSize = 0)
    {
        $this->stream = $stream;
        $this->streamLength = $length;
        $this->chunkSize = $chunkSize;
        $this->chunkCacheSize = $chunkCacheSize;
        $this->streamOffsetInitial = ftell($stream);
        $this->streamOffsetCurrent = $this->streamOffsetInitial;
    }

    public function getTotalLength()
    {
        return $this->streamLength;
    }

    /**
     * Get chunk size
     *
     * @return int
     */
    public function getChunkSize()
    {
        return $this->chunkSize;
    }

    /**
     * Get number of past chunks that may be cached
     *
     * @return int
     */
    public function getChunkCacheSize()
    {
        return $this->chunkCacheSize;
    }
    
    /**
     * Get number of past chunks that are currently in the cache
     *
     * @return int
     */
    public function getCurrentChunkCacheSize()
    {
        return sizeof($this->chunkCache);
    }

    /**
     * See if chunk for the given position is currently cached
     *
     * @param int $position
     * @return bool
     */
    public function isPositionInChunkCache($position)
    {
        $chunkOffset = $position - $position % $this->chunkSize;

        return isset($this->chunkCache["chunk_{$chunkOffset}"]);
    }

    /**
     * Set chunk cache size
     *
     * The extra cached chunks are dropped immediately.
     *
     * @param int $chunkCacheSize
     * @return static
     */
    public function setChunkCacheSize($chunkCacheSize)
    {
        $this->chunkCacheSize = $chunkCacheSize;

        if (($currentChunkCacheSize = sizeof($this->chunkCache)) > $this->chunkCacheSize) {
            array_splice($this->chunkCache, 0, $currentChunkCacheSize - $this->chunkCacheSize);
        }
        
        return $this;
    }

    /**
     * Clear chunk cache
     */
    public function clearChunkCache()
    {
        $this->chunkCache = array();
    }

    public function loadData($position)
    {
        // check position and available bytes
        if ($position < 0 || null !== $this->streamLength && $position >= $this->streamLength) {
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
                    $this->cacheChunk($currentChunkOffset, $currentChunk, $currentChunkLength);
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
                    $this->cacheChunk($this->offset, $this->data, $this->length);
                }
                $success = $this->loadChunkFromStream($position, $chunkOffset);
            }
        }

        $this->seeded = true;

        return $success;
    }

    /**
     * Load chunk from the stream
     *
     * @param int $position
     * @param int $chunkOffset
     * @return bool
     */
    protected function loadChunkFromStream($position, $chunkOffset)
    {
        // seek (if needed)
        if ($this->streamOffsetCurrent !== ($seekOffset = $this->streamOffsetInitial + $chunkOffset)) {
            fseek($this->stream, $seekOffset);
            $this->streamOffsetCurrent = $seekOffset;
        }

        // determine how many bytes to read
        if (null !== $this->streamLength && $this->streamLength - $position < $this->chunkSize) {
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

        return $this->length > 0 && (null === $this->streamLength || 0 === $bytesToRead);
    }

    /**
     * Store given chunk in the cache
     *
     * @param int    $chunkOffset
     * @param string $chunk
     * @param int    $chunkLength
     */
    protected function cacheChunk($chunkOffset, $chunk, $chunkLength)
    {
        $this->chunkCache["chunk_{$chunkOffset}"] = array($chunk, $chunkLength);

        if (sizeof($this->chunkCache) > $this->chunkCacheSize) {
            array_shift($this->chunkCache);
        }
    }

    /**
     * Load cached chunk
     *
     * @param int $chunkOffset
     */
    protected function loadCachedChunk($chunkOffset)
    {
        $this->offset = $chunkOffset;
        list($this->data, $this->length) = $this->chunkCache["chunk_{$chunkOffset}"];
        unset($this->chunkCache["chunk_{$chunkOffset}"]);
    }
}
