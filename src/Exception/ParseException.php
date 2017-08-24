<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

abstract class ParseException extends \Exception implements ExceptionInterface
{
    /** @var int */
    private $parserOffset;
    /** @var int|null */
    private $parserLine;

    function __construct(string $message, int $parserOffset, ?int $parserLine = null, ?\Throwable $previous = null)
    {
        // extend message
        if ($parserLine !== null) {
            $message .= ' on line ' . $parserLine;
        }
        $message .= ' (at offset ' . $parserOffset . ')';

        parent::__construct($message, 0, $previous);

        $this->parserLine = $parserLine;
        $this->parserOffset = $parserOffset;
    }

    function getParserOffset(): int
    {
        return $this->parserOffset;
    }

    function getParserLine(): ?int
    {
        return $this->parserLine;
    }
}
