<?php declare(strict_types=1);

namespace Kuria\Parser\Input;

use Kuria\Parser\Exception\InputException;
use PHPUnit\Framework\TestCase;

abstract class InputTest extends TestCase
{
    /**
     * Create input instance for the given data
     */
    abstract protected function createInput(string $data): Input;

    /**
     * See if the total length of data is known
     */
    abstract protected function isTotalLengthKnown(): bool;

    function testProperties()
    {
        $input = $this->createInput('hello');

        $this->assertInternalType('string', $input->data);
        $this->assertInternalType('integer', $input->length);
        $this->assertInternalType('integer', $input->offset);
    }

    function testGetTotalLength()
    {
        $input = $this->createInput('hello');

        $this->assertSame($this->isTotalLengthKnown() ? 5 : null, $input->getTotalLength());
    }

    function testLoadData()
    {
        $data = <<<INPUT
Lorem ipsum dolor sit amet consectetuer elit vel risus Nulla nisl.
Maecenas nulla id faucibus Vestibulum Vestibulum eros condimentum enim Lorem id.
INPUT;

        $input = $this->createInput($data);

        // forward read
        $read = '';
        for ($i = 0; isset($input->data[$i - $input->offset]) || $input->loadData($i); ++$i) {
            $read .= $input->data[$i - $input->offset];
        }

        $this->assertSame($data, $read);

        // backward read
        $read = '';
        for (--$i; $i - $input->offset >= 0 || $input->loadData($i); --$i) {
            $read .= $input->data[$i - $input->offset];
        }

        $this->assertSame(strrev($data), $read);
    }

    function testChunk()
    {
        $input = $this->createInput('aaaaabbbbbcccccx');

        // in-range chunks
        $this->assertSame('aaaaa', $input->getChunk(0, 5));
        $this->assertSame('aaaab', $input->getChunk(1, 5));
        $this->assertSame('bccccc', $input->getChunk(9, 6));
        $this->assertSame('bccccc', $input->getChunk(9, 6));
        $this->assertSame('aaaaabbbbbcccccx', $input->getChunk(0, 16));

        // chunks beyond available range should contain all available data
        $this->assertSame('x', $input->getChunk(15, 10));

        // chunking past available range should yield an empty chunk
        $this->assertSame('', $input->getChunk(100, 5));
    }

    function testChunkThrowsExceptionOnNegativePosition()
    {
        $this->expectException(InputException::class);

        $this->createInput('aaa')->getChunk(-1, 5);
    }

    function testChunkThrowsExceptionOnZeroLength()
    {
        $this->expectException(InputException::class);

        $this->createInput('aaa')->getChunk(0, 0);
    }

    function testChunkThrowsExceptionOnNegativeLength()
    {
        $this->expectException(InputException::class);

        $this->createInput('aaa')->getChunk(0, -1);
    }
}
