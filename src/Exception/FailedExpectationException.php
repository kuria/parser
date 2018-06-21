<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

abstract class FailedExpectationException extends ParseException
{
    protected const QUOTE_EXPECTATIONS = true;

    function __construct(string $actual, ?array $expected, int $offset, ?int $line = null, ?\Throwable $previous = null)
    {
        $message = "Unexpected {$actual}";

        if ($expected) {
            $message .= self::formatExpectations($expected);
        }

        parent::__construct($message, $offset, $line, $previous);
    }

    private static function formatExpectations(array $expected): string
    {
        $out = ', expected ';

        for ($i = 0, $last = count($expected) - 1; $i <= $last; ++$i) {
            // add delimiter
            if ($i > 0) {
                $out .= $last === $i ? ' or ' : ', ';
            }

            // add option
            $out .= static::QUOTE_EXPECTATIONS ? "\"{$expected[$i]}\"" : $expected[$i];
        }

        return $out;
    }
}
