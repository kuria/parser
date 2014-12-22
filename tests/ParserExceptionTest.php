<?php

namespace Kuria\Parser;

use Kuria\Parser\Input\MemoryInput;

class ParserExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateForLineAndOffset()
    {
        $e = ParserException::createForLineAndOffset(123, 456, 'Test');

        $this->assertSame(123, $e->getParserLine());
        $this->assertSame(456, $e->getParserOffset());

        $this->assertContains('Test', $e->getMessage());
        $this->assertContains('123', $e->getMessage());
        $this->assertContains('456', $e->getMessage());
    }

    public function testCreateForCurrentState()
    {
        $parser = new InputParser(new MemoryInput("a\nb"));
        $parser->eat();

        $e = ParserException::createForCurrentState($parser, 'Test');

        $this->assertSame($parser->line, $e->getParserLine());
        $this->assertSame($parser->i, $e->getParserOffset());

        $this->assertContains('Test', $e->getMessage());
        $this->assertContains((string) $parser->line, $e->getMessage());
        $this->assertContains((string) $parser->i, $e->getMessage());
    }
}
