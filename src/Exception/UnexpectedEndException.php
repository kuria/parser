<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

class UnexpectedEndException extends FailedExpectationException
{
    function __construct(
        ?array $expected = null,
        int $parserPosition,
        ?int $parserLine = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            'end',
            ExceptionHelper::formatList($expected),
            $parserPosition,
            $parserLine,
            $previous
        );
    }
}
