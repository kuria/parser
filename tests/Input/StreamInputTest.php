<?php

namespace Kuria\Parser\Input;

class StreamInputTest extends InputTest
{
    protected function createInput($data)
    {
        $stream = $this->createStream($data);

        return new StreamInput(
            $stream,
            $this->isTotalLengthKnown() ? strlen($data) : null,
            1024
        );
    }

    /**
     * Create stream for given data
     *
     * @param string $data
     * @return resource
     */
    protected function createStream($data)
    {
        $stream = fopen('php://memory', 'r+');

        if ('' !== $data) {
            fwrite($stream, $data);
            fseek($stream, 0);
        }

        return $stream;
    }

    protected function isTotalLengthKnown()
    {
        return true;
    }

    public function testReadBehaviorAndChunkCache()
    {
        $that = $this;

        // create stream
        $data = 'foo1234567890ABCDEabcde';
        $stream = $this->createStream($data);
        $offset = 3;
        $totalLength = strlen($data) - $offset;
        fseek($stream, $offset); // to test that offseted streams are handled correctly

        // create input
        $input = new StreamInput(
            $stream,
            $this->isTotalLengthKnown() ? strlen($data) - $offset : null,
            5,
            2
        );

        // helpers
        $gotoChunk = function ($n, $shouldSucceed = true) use ($input, $that) {
            $that->assertSame($shouldSucceed, $input->loadData($n * 5));
        };
        $gotoPosition = function ($i, $shouldSucceed = true) use ($input, $that) {
            $that->assertSame($shouldSucceed, $input->loadData($i));
        };

        // available chunks: [CHUNK 0] [CHUNK 1] [CHUNK 2] [CHUNK 3]

        // initial state
        $this->assertSame(2, $input->getChunkCacheSize());
        $this->assertStreamInputState($input, $stream, '', 0, $offset);

        $gotoChunk(0);

        // [CHUNK 0, cache = empty | miss]
        $this->assertStreamInputState($input, $stream, '12345', 0, $offset + 5);
        $this->assertStreamInputChunkCacheState($input, $totalLength);

        // [CHUNK 0, cache = empty | miss]
        $gotoPosition(4);
        $this->assertStreamInputState($input, $stream, '12345', 0, $offset + 5);
        $this->assertStreamInputChunkCacheState($input, $totalLength);
        
        // [CHUNK 1, cache = 0 | miss]
        $gotoChunk(1);
        $this->assertStreamInputState($input, $stream, '67890', 5, $offset + 10);
        $this->assertStreamInputChunkCacheState($input, $totalLength, 0);

        // [CHUNK 0, cache = 1 | hit]
        $gotoPosition(3);
        $this->assertStreamInputState($input, $stream, '12345', 0, $offset + 10);
        $this->assertStreamInputChunkCacheState($input, $totalLength, 1);

        // [CHUNK 1, cache = 0 | hit]
        $gotoPosition(8);
        $this->assertStreamInputState($input, $stream, '67890', 5, $offset + 10);
        $this->assertStreamInputChunkCacheState($input, $totalLength, 0);

        // [CHUNK 2, cache = 0 1 | miss]
        $gotoChunk(2);
        $this->assertStreamInputState($input, $stream, 'ABCDE', 10, $offset + 15);
        $this->assertStreamInputChunkCacheState($input, $totalLength, 0, 1);

        // [CHUNK 3, cache = 1 2 | miss]
        $gotoChunk(3);
        $this->assertStreamInputState($input, $stream, 'abcde', 15, $offset + 20);
        $this->assertStreamInputChunkCacheState($input, $totalLength, 1, 2);

        // [CHUNK 0, cache = 2 3 | miss]
        // the current lookup drops chunk 1 and pushes chunk 3 there
        $gotoChunk(0);
        $this->assertStreamInputState($input, $stream, '12345', 0, $offset + 5);
        $this->assertStreamInputChunkCacheState($input, $totalLength, 2, 3);

        // [CHUNK 1, cache = 3 0 | miss]
        $gotoChunk(1);
        $this->assertStreamInputState($input, $stream, '67890', 5, $offset + 10);
        $this->assertStreamInputChunkCacheState($input, $totalLength, 3, 0);

        // [CHUNK 2, cache = 0 1 | miss]
        $gotoChunk(2);
        $this->assertStreamInputState($input, $stream, 'ABCDE', 10, $offset + 15);
        $this->assertStreamInputChunkCacheState($input, $totalLength, 0, 1);

        // [CHUNK 3, cache = 1 2 | miss]
        $gotoChunk(3);
        $this->assertStreamInputState($input, $stream, 'abcde', 15, $offset + 20);
        $this->assertStreamInputChunkCacheState($input, $totalLength, 1, 2);
        
        // the following part depends on whether the total stream length is known to the input or not
        $gotoChunk(4, false);
        if ($this->isTotalLengthKnown()) {
            // [CHUNK 4 (END), cache = 1 2 | noop]
            // reaching the end with known length should not touch current state
            $this->assertStreamInputState($input, $stream, 'abcde', 15, $offset + 20);
            $this->assertStreamInputChunkCacheState($input, $totalLength, 1, 2);

            // [CHUNK 0, cache = 2 3 | miss]
            $gotoChunk(0);
            $this->assertStreamInputState($input, $stream, '12345', 0, $offset + 5);
            $this->assertStreamInputChunkCacheState($input, $totalLength, 2, 3);

            // test loading < 0
            // [keep CHUNK 0, cache = 2 3 | noop]
            $gotoPosition(-1, false);
            $this->assertStreamInputState($input, $stream, '12345', 0, $offset + 5);
            $this->assertStreamInputChunkCacheState($input, $totalLength, 2, 3);
        } else {
            // [CHUNK 4 (END), cache = 2 3 | miss]
            $this->assertStreamInputState($input, $stream, '', 20, $offset + 20);
            $this->assertStreamInputChunkCacheState($input, $totalLength, 2, 3);

            // [CHUNK 0, cache = 3 4 | miss]
            $gotoChunk(0);
            $this->assertStreamInputState($input, $stream, '12345', 0, $offset + 5);
            $this->assertStreamInputChunkCacheState($input, $totalLength, 3, 4);

            // test loading < 0
            // [keep CHUNK 0, cache = 3 4 | noop]
            $gotoPosition(-1, false);
            $this->assertStreamInputState($input, $stream, '12345', 0, $offset + 5);
            $this->assertStreamInputChunkCacheState($input, $totalLength, 3, 4);

        }

        // set chunk cache size to 1
        // this should drop the excess cached chunks
        $input->setChunkCacheSize(1);
        $this->assertStreamInputChunkCacheState($input, $totalLength, $this->isTotalLengthKnown() ? 3 : 4);

        // clear cache
        $input->clearChunkCache();
        $this->assertStreamInputChunkCacheState($input, $totalLength);
    }

    /**
     * Assert stream input state
     *
     * @param StreamInput $input
     * @param resource    $stream
     * @param string      $expectedData
     * @param int         $expectedOffset
     * @param int         $expectedStreamPosition
     */
    protected function assertStreamInputState(
        StreamInput $input,
        $stream,
        $expectedData,
        $expectedOffset,
        $expectedStreamPosition
    ) {
        $this->assertSame($expectedData, $input->data);
        $this->assertSame(strlen($input->data), $input->length);
        $this->assertSame($expectedOffset, $input->offset);
        $this->assertSame($expectedStreamPosition, ftell($stream));
    }

    /**
     * Assert state of the stream input's chunk cache
     *
     * @param StreamInput $input
     * @param int         $totalLength
     * @param int         $expectedChunkNumberInCache,...
     */
    protected function assertStreamInputChunkCacheState(StreamInput $input, $totalLength)
    {
        $expectedChunkNumbersInCacheMap = array_flip(array_slice(func_get_args(), 2));
        $chunkSize = $input->getChunkSize();
        $numChunks = ceil($totalLength / $chunkSize);

        for ($i = 0; $i <= $numChunks; ++$i) {
            $chunkShouldBeCached = isset($expectedChunkNumbersInCacheMap[$i]);
            
            $this->assertSame(
                $chunkShouldBeCached,
                $input->isPositionInChunkCache($i * $chunkSize),
                sprintf('expected chunk %d to %s cached', $i, $chunkShouldBeCached ? 'be' : 'not be')
            );
        }

        $this->assertSame(sizeof($expectedChunkNumbersInCacheMap), $input->getCurrentChunkCacheSize());
    }
}
