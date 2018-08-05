<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

class OutOfBoundariesException extends ParseException
{
    function __construct(int $parserPosition, ?\Throwable $previous = null)
    {
        parent::__construct('Out of boundaries', $parserPosition, null, $previous);
    }
}
