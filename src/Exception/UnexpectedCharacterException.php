<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

class UnexpectedCharacterException extends FailedExpectationException
{
    function __construct(
        string $actualChar,
        ?array $expected = null,
        int $parserPosition,
        ?int $parserLine = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            ExceptionHelper::formatItem($actualChar),
            ExceptionHelper::formatList($expected),
            $parserPosition,
            $parserLine,
            $previous
        );
    }
}
