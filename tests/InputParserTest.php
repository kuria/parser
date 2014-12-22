<?php

namespace Kuria\Parser;

use Kuria\Parser\Input\MemoryInput;

abstract class InputParserTest extends ParserTest
{
    protected function createParser($data = '', $trackLineNumber = true)
    {
        return new InputParser(new MemoryInput($data), $trackLineNumber);
    }

    abstract public function testGetInput();

    public function testCreateFromString()
    {
        $parserClass = get_class($this->createParser());

        $parser = call_user_func(array($parserClass, 'fromString'), "\nhello", false);

        $this->assertInstanceOf($parserClass, $parser);
        $this->assertNull($parser->line);
        $this->assertSame("\nhello", $parser->eatRest());
    }
}
