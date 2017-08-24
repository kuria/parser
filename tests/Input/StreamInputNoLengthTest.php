<?php declare(strict_types=1);

namespace Kuria\Parser\Input;

class StreamInputNoLengthTest extends StreamInputTest
{
    protected function isTotalLengthKnown(): bool
    {
        return false;
    }
}
