<?php

namespace Kuria\Parser;

/**
 * Parser exception
 *
 * @author ShiraNai7 <shira.cz>
 */
class ParserException extends \RuntimeException
{
    /** @var int|null */
    protected $parserLine;
    /** @var int|null */
    protected $parserOffset;

    /**
     * Create an instance for the given line and offset
     *
     * @param int|null        $line
     * @param int             $offset
     * @param string          $message
     * @param int             $code
     * @param \Exception|null $previous
     * @return static
     */
    public static function createForLineAndOffset($line, $offset, $message, $code = 0, \Exception $previous = null)
    {
        // extend error message
        if (null !== $line) {
            $message .= ' on line ' . $line;
        }
        $message .= ' (at offset ' . $offset . ')';

        // create instance
        $e = new self($message, $code, $previous);

        $e->parserLine = $line;
        $e->parserOffset = $offset;

        return $e;
    }

    /**
     * Create an instance using given parser's current state
     *
     * @param Parser          $parser
     * @param string          $message
     * @param int             $code
     * @param \Exception|null $previous
     * @return static
     */
    public static function createForCurrentState(Parser $parser, $message, $code = 0, \Exception $previous = null)
    {
        return static::createForLineAndOffset($parser->line, $parser->i, $message, $code, $previous);
    }

    /**
     * Get parser line
     *
     * @return int|null
     */
    public function getParserLine()
    {
        return $this->parserLine;
    }

    /**
     * Get parser offset
     *
     * @return int|null
     */
    public function getParserOffset()
    {
        return $this->parserOffset;
    }
}
