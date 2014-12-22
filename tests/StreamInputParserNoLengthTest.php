<?php

namespace Kuria\Parser;

class StreamInputParserNoLengthTest extends StreamInputParserTest
{
    protected function shouldSpecifyStreamLength()
    {
        return false;
    }

    public function testGetLength()
    {
        $this->assertNull($this->createParser('')->getLength());
        $this->assertNull($this->createParser('hello')->getLength());
    }
}
