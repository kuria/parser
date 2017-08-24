<?php declare(strict_types=1);

namespace Kuria\Parser;

use Kuria\Parser\Input\Input;
use Kuria\Parser\Input\MemoryInput;

class MemoryInputParserTest extends ParserTest
{
    protected function createInput(string $data): Input
    {
        return new MemoryInput($data);
    }
}
