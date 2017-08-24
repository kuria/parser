<?php declare(strict_types=1);

namespace Kuria\Parser\Input;

class MemoryInputTest extends InputTest
{
    protected function createInput(string $data): Input
    {
        return new MemoryInput($data);
    }

    protected function isTotalLengthKnown(): bool
    {
        return true;
    }
}
