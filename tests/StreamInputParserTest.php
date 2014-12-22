<?php

namespace Kuria\Parser;

use Kuria\Parser\Input\StreamInput;

class StreamInputParserTest extends InputParserTest
{
    protected function createParser($data = '', $trackLineNumber = true)
    {
        $stream = fopen('php://memory', 'r+');

        if ('' !== $data) {
            fwrite($stream, $data);
            fseek($stream, 0);
        }

        return new InputParser(
            new StreamInput(
                $stream,
                $this->shouldSpecifyStreamLength() ? strlen($data) : null,
                1024
            ),
            $trackLineNumber
        );
    }

    /**
     * See if this test should specify the length of the stream
     *
     * @return bool
     */
    protected function shouldSpecifyStreamLength()
    {
        return true;
    }

    public function testGetInput()
    {
        $this->assertInstanceOf(__NAMESPACE__ . '\Input\StreamInput', $this->createParser()->getInput());
    }
}
