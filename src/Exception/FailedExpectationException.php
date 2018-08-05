<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

abstract class FailedExpectationException extends ParseException
{
    function __construct(
        string $actual,
        ?string $expected,
        int $parserPosition,
        ?int $parserLine = null,
        ?\Throwable $previous = null
    ) {
        $message = "Unexpected {$actual}";

        if ($expected !== null) {
            $message .= ", expected {$expected}";
        }

        parent::__construct($message, $parserPosition, $parserLine, $previous);
    }
}
