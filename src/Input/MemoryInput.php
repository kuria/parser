<?php

namespace Kuria\Parser\Input;

/**
 * Memory input
 *
 * @author ShiraNai7 <shira.cz>
 */
class MemoryInput extends Input
{
    /**
     * @param string $data
     */
    public function __construct($data)
    {
        $this->data = $data;
        $this->length = strlen($data);
    }

    public function getTotalLength()
    {
        return $this->length;
    }

    public function loadData($position)
    {
        return $position >= 0 && $position < $this->length;
    }
}
