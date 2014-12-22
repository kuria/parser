<?php

namespace Kuria\Parser;

use Kuria\Parser\Input\MemoryInput;

class MemoryInputParserTest extends InputParserTest
{
    protected function createParser($data = '', $trackLineNumber = true)
    {
        return new InputParser(new MemoryInput($data), $trackLineNumber);
    }

    public function testGetInput()
    {
        $this->assertInstanceOf(__NAMESPACE__ . '\Input\MemoryInput', $this->createParser()->getInput());
    }
}
