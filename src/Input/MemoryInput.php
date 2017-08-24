<?php declare(strict_types=1);

namespace Kuria\Parser\Input;

class MemoryInput extends Input
{
    function __construct(string $data)
    {
        $this->data = $data;
        $this->length = strlen($data);
    }

    function getTotalLength(): ?int
    {
        return $this->length;
    }

    function loadData(int $position): bool
    {
        return $position >= 0 && $position < $this->length;
    }
}
