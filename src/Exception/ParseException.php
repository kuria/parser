<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

abstract class ParseException extends \Exception implements ExceptionInterface
{
    /** @var int */
    private $parserPosition;

    /** @var int|null */
    private $parserLine;

    function __construct(string $message, int $parserPosition, ?int $parserLine = null, ?\Throwable $previous = null)
    {
        // extend message
        if ($parserLine !== null) {
            $message .= ' on line ' . $parserLine;
        }
        $message .= ' (at position ' . $parserPosition . ')';

        parent::__construct($message, 0, $previous);

        $this->parserPosition = $parserPosition;
        $this->parserLine = $parserLine;
    }

    function getParserPosition(): int
    {
        return $this->parserPosition;
    }

    function getParserLine(): ?int
    {
        return $this->parserLine;
    }
}
