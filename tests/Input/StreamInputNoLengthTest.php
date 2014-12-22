<?php

namespace Kuria\Parser\Input;

class StreamInputNoLengthTest extends StreamInputTest
{
    protected function isTotalLengthKnown()
    {
        return false;
    }
}
