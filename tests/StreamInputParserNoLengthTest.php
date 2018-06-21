<?php declare(strict_types=1);

namespace Kuria\Parser;

class StreamInputParserNoLengthTest extends StreamInputParserTest
{
    protected function shouldSpecifyStreamLength(): bool
    {
        return false;
    }

    function testShouldGetLength()
    {
        $this->assertNull($this->createParser('')->getLength());
        $this->assertNull($this->createParser('hello')->getLength());
    }
}
