<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

class UnexpectedCharacterTypeException extends FailedExpectationException
{
    protected const QUOTE_EXPECTATIONS = false;

    function __construct(string $actualCharTypeName, ?array $expected = null, int $offset, ?int $line = null, ?\Throwable $previous = null)
    {
        parent::__construct($actualCharTypeName, $expected, $offset, $line, $previous);
    }
}
