<?php declare(strict_types=1);

namespace Kuria\Parser;

use Kuria\Parser\Input\Input;
use Kuria\Parser\Input\StreamInput;

class StreamInputParserTest extends ParserTest
{
    protected function createInput(string $data): Input
    {
        $stream = fopen('php://memory', 'r+');

        if ($data !== '') {
            fwrite($stream, $data);
            fseek($stream, 0);
        }

        return new StreamInput(
            $stream,
            $this->shouldSpecifyStreamLength() ? strlen($data) : null,
            1024
        );
    }

    /**
     * See if this test should specify the length of the stream
     */
    protected function shouldSpecifyStreamLength(): bool
    {
        return true;
    }

}
