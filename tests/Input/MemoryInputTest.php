<?php

namespace Kuria\Parser\Input;

class MemoryInputTest extends InputTest
{
    protected function createInput($data)
    {
        return new MemoryInput($data);
    }

    protected function isTotalLengthKnown()
    {
        return true;
    }
}
