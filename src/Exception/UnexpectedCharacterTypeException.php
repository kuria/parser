<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

class UnexpectedCharacterTypeException extends FailedExpectationException
{
    function __construct(
        string $actualCharTypeName,
        ?array $expected = null,
        int $parserPosition,
        ?int $parserLine = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $actualCharTypeName,
            ExceptionHelper::formatList($expected, false),
            $parserPosition,
            $parserLine,
            $previous
        );
    }
}
