<?php

namespace Kuria\Parser\Input;

abstract class InputTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Create input instance for the given data
     *
     * @param string $data
     * @return Input
     */
    abstract protected function createInput($data);

    /**
     * See if the total length of data is known
     *
     * @return bool
     */
    abstract protected function isTotalLengthKnown();

    public function testProperties()
    {
        $input = $this->createInput('hello');

        $this->assertInternalType('string', $input->data);
        $this->assertInternalType('integer', $input->length);
        $this->assertInternalType('integer', $input->offset);
    }

    public function testGetTotalLength()
    {
        $input = $this->createInput('hello');

        $this->assertSame($this->isTotalLengthKnown() ? 5 : null, $input->getTotalLength());
    }

    public function testLoadData()
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

    public function testChunk()
    {
        $input = $this->createInput('aaaaabbbbbcccccx');

        // in-range chunks
        $this->assertSame('aaaaa', $input->chunk(0, 5));
        $this->assertSame('aaaab', $input->chunk(1, 5));
        $this->assertSame('bccccc', $input->chunk(9, 6));
        $this->assertSame('bccccc', $input->chunk(9, 6));
        $this->assertSame('aaaaabbbbbcccccx', $input->chunk(0, 16));

        // chunks beyond available range should contain all available data
        $this->assertSame('x', $input->chunk(15, 10));

        // chunking past available range should yield an empty chunk
        $this->assertSame('', $input->chunk(100, 5));
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Invalid position
     */
    public function testChunkThrowsExceptionOnNegativePosition()
    {
        $this->createInput('aaa')->chunk(-1, 5);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Invalid length
     */
    public function testChunkThrowsExceptionOnZeroLength()
    {
        $this->createInput('aaa')->chunk(0, 0);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Invalid length
     */
    public function testChunkThrowsExceptionOnNegativeLength()
    {
        $this->createInput('aaa')->chunk(0, -1);
    }
}
