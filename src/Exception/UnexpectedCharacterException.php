<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

class UnexpectedCharacterException extends FailedExpectationException
{
    function __construct(string $actualChar, ?array $expected = null, int $offset, ?int $line = null, ?\Throwable $previous = null)
    {
        parent::__construct("\"{$actualChar}\"", $expected, $offset, $line, $previous);
    }
}
