<?php

namespace Kuria\Parser;

/**
 * Base parser
 *
 * @author ShiraNai7 <shira.cz>
 */
abstract class Parser
{
    /** No character */
    const CHAR_NONE = 1;
    /** Whitespace character */
    const CHAR_WS = 2;
    /** Numeric character */
    const CHAR_NUM = 3;
    /** Identifier character */
    const CHAR_IDT = 4;
    /** Control character */
    const CHAR_CTRL = 5;
    /** Unmapped character */
    const CHAR_OTHER = 6;

    /** @var int current index */
    public $i;
    /** @var string|null current character or null on string end */
    public $char;
    /** @var int type of the current character */
    public $charType;
    /** @var array map of all chars to char types (char => char type) */
    public $charTypeMap = array();
    /** @var string|null previous character (null on start) */
    public $lastChar;
    /** @var int|null current line, if line tracking is enabled (newline at the current position has already been counted) */
    public $line;
    /** @var bool end of input 1/0 */
    public $end;
    /** @var array generic variables attached to current state */
    public $vars = array();
    
    /** @var array stored states */
    protected $states = array();
    /** @var bool track line numbers 1/0 */
    protected $trackLineNumber = true;

    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Initialize the parser
     *
     * It is the constructor's job to call this method.
     */
    protected function initialize()
    {
        $this->charTypeMap = $this->buildCharTypeMap();
    }

    /**
     * Get length, if known
     *
     * @return int|null
     */
    abstract public function getLength();

    /**
     * See if line number tracking is enabled
     *
     * @return bool
     */
    public function isTrackingLineNumbers()
    {
        return $this->trackLineNumber;
    }

    /**
     * Go to the next character and return the current one
     *
     * @return string|null null null on boundary
     */
    abstract public function eat();

    /**
     * Go to the previous character and return the current one
     *
     * @return string|null null on boundary
     */
    abstract public function spit();

    /**
     * Go to the next character and return it
     *
     * @return string|null null on boundary
     */
    abstract public function shift();

    /**
     * Go to the previous character and return it
     *
     * @return string|null null on boundary
     */
    abstract public function unshift();

    /**
     * Get character at the given offset
     *
     * Does not affect current state.
     *
     * @param int  $offset   relative offset from current position
     * @param bool $absolute treat $offset as an absolute position 1/0
     * @return string|null null on boundary
     */
    abstract public function peek($offset = 1, $absolute = false);

    /**
     * Get chunk of input data
     *
     * Does not affect current state.
     *
     * @param int $position absolute starting position (>= 0)
     * @param int $length   up to N bytes will be read (>= 1)
     * @throws \InvalidArgumentException if the position or length is invalid
     * @return string
     */
    abstract public function chunk($position, $length);

    /**
     * Alter current position
     *
     * @param int  $offset   relative offset, can be negative
     * @param bool $absolute treat $offset as an absolute position 1/0
     * @throws ParserException when navigating beyond available boundaries
     * @return static
     */
    public function seek($offset, $absolute = false)
    {
        if (0 === $offset) {
            return $absolute ? $this->rewind() : $this;
        }

        $position = $absolute ? $offset : $this->i + $offset;

        if ($position < 0) {
            throw new ParserException(sprintf('Cannot seek to position "%d" - out of boundaries', $position));
        }

        if ($this->trackLineNumber || !$this->jump($position)) {
            $direction = $position > $this->i ? 1 : -1;

            while ($this->i !== $position) {
                if (1 === $direction) {
                    if (null === $this->shift() && $this->i !== $position) {
                        throw new ParserException(sprintf('Cannot seek to position "%d" - out of boundaries (unexpected end)', $position));
                    }
                } else {
                    $this->unshift();
                }
            }
        }

        return $this;
    }

    /**
     * Jump to the specified position
     *
     * Public version: {@see seek()}
     *
     * Internal. Only safe to use with line tracking disabled.
     *
     * @param int $position
     * @throws ParserException if the position is invalid
     * @return bool false if not supported, true otherwise
     */
    abstract protected function jump($position);

    /**
     * Reset state
     *
     * @return static
     */
    public function reset()
    {
        $this->states = array();
        $this->rewind();

        return $this;
    }

    /**
     * Rewind to the beginning
     *
     * @return static
     */
    abstract public function rewind();

    /**
     * See if the parser is at the start of a newline sequence
     *
     * Internally, the logic from this function is copy-pasted
     * inline for performance reasons
     *
     * @return bool
     */
    public function atNewline()
    {
        return "\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char;
    }

    /**
     * Consume specific character and return the next character
     *
     * @param string $char the character to consume
     * @throws ParserException if current character is not $char
     * @return string null null on boundary
     */
    public function eatChar($char)
    {
        if ($char === $this->char) {
            return $this->shift();
        }
        $this->unexpectedCharException($char);
    }

    /**
     * Attempt to consume specific character and return success state
     *
     * @param string $char the character to consume
     * @return bool consumed successfully 1/0
     */
    public function eatIfChar($char)
    {
        if ($char === $this->char) {
            $this->shift();

            return true;
        }

        return false;
    }

    /**
     * Consume all character of specified type
     *
     * Pre-offset: any
     * Post-offset: at first invalid character or end
     *
     * @param int $type character type (see Parser::CHAR_* constants)
     * @return string all consumed characters
     */
    public function eatType($type)
    {
        // scan
        $consumed = '';
        while (!$this->end) {
            // check type
            if ($this->charTypeMap[$this->char] !== $type) {
                break;
            }

            // consume
            $consumed .= $this->eat();
        }
        
        return $consumed;
    }

    /**
     * Consume all characters of specified types
     *
     * Pre-offset: any
     * Post-offset: at first invalid character or end
     *
     * @param array $typeMap map of types (see Parser::CHAR_* constants)
     * @return string all consumed characters
     */
    public function eatTypes(array $typeMap)
    {
        // scan
        $consumed = '';
        while (!$this->end) {
            // check type
            if (!isset($typeMap[$this->charTypeMap[$this->char]])) {
                break;
            }

            // consume
            $consumed .= $this->eat();
        }
        
        return $consumed;
    }

    /**
     * Consume whitespace if any
     *
     * Pre-offset: any
     * Post-offset: at first non-whitespace character or end
     *
     * @param bool $newlines consume newline characters (\r or \n)
     * @return string|null returns first non-whitespace character (or a newline if $newlines = false) or null (= end)
     */
    public function eatWs($newlines = true)
    {
        // scan
        while (!$this->end) {
            // check type
            if (
                static::CHAR_WS !== $this->charTypeMap[$this->char]
                || !$newlines && ("\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char)
            ) {
                break;
            }

            // shift
            $this->shift();
        }
        
        return $this->char;
    }

    /**
     * Consume all characters until the specified delimiters
     *
     * Pre-offset: any
     * Post-offset: at or after first delimiter or at end
     *
     * @param array|string $delimiterMap  map of delimiter characters or a single character
     * @param bool         $skipDelimiter skip the delimiter 1/0
     * @param bool         $allowEnd      treat end as valid delimiter 1/0
     * @throws ParserException if end is encountered and $allowEnd is false
     * @return string all consumed characters
     */
    public function eatUntil($delimiterMap, $skipDelimiter = true, $allowEnd = false)
    {
        if (!is_array($delimiterMap)) {
            $delimiterMap = array($delimiterMap => true);
        }

        // scan
        $consumed = '';
        while (!$this->end && !isset($delimiterMap[$this->char])) {
            $consumed .= $this->eat();
        }

        // check end
        if ($this->end && !$allowEnd) {
            $this->unexpectedEndException(array_keys($delimiterMap));
        }

        // skip delimiter
        if ($skipDelimiter && !$this->end) {
            $this->shift();
        }
        
        return $consumed;
    }

    /**
     * Consume all character until end of line or the end
     *
     * Pre-offset: any
     * Post-offset: after or at the newline
     *
     * @param bool $skip skip the newline 1/0
     * @return string all consumed characters
     */
    public function eatUntilEol($skip = true)
    {
        $consumed = '';
        while (!$this->end && !("\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char)) {
            $consumed .= $this->eat();
        }

        if ($skip) {
            $this->eatEol();
        }

        return $consumed;
    }

    /**
     * Eat end of line
     *
     * Pre-offset: at EOL
     * Post-offset: after EOL
     *
     * @return string all consumed characters
     */
    public function eatEol()
    {
        $out = '';

        while (!$this->end && (("\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char) || "\n" === $this->char && "\r" === $this->lastChar)) {
            $out .= $this->eat();
        }

        return $out;
    }

    /**
     * Eat all reamaining characters
     *
     * @return string all consumed characters
     */
    public function eatRest()
    {
        $out = '';

        while (!$this->end) {
            $out .= $this->eat();
        }

        return $out;
    }

    /**
     * Get character type
     *
     * @param string|bool|null $char char, null or false (= current char)
     * @return int
     */
    public function charType($char = false)
    {
        return $this->charTypeMap[false === $char ? $this->char : $char];
    }

    /**
     * Build char type map
     *
     * @return array
     */
    protected function buildCharTypeMap()
    {
        $charMap = array(
            '' => static::CHAR_NONE, // special case for NULL
        );

        $wsMap = $this->getWhitespaceMap();
        $idtExtraMap = $this->getIdtExtraMap();

        foreach ($this->getCharMap() as $ord => $char) {
            if ($ord > 64 && $ord < 91 || $ord > 96 && $ord < 123 || $ord > 126 || isset($idtExtraMap[$char])) {
                $type = static::CHAR_IDT;
            } elseif ($ord > 47 && $ord < 58) {
                $type = static::CHAR_NUM;
            } elseif (isset($wsMap[$char])) {
                $type = static::CHAR_WS;
            } elseif ($ord > 31) {
                $type = static::CHAR_CTRL;
            } else {
                $type = static::CHAR_OTHER;
            }

            $charMap[$char] = $type;
        }

        return $charMap;
    }

    /**
     * Get map of characters which are considered whitespace
     *
     * @return array
     */
    protected function getWhitespaceMap()
    {
        return array(' ' => 0, "\n" => 1, "\r" => 2, "\t" => 3, "\h" => 4);
    }

    /**
     * Get map of extra characters (beyond a-z A-Z) that are considered identifier characters
     *
     * @return array
     */
    protected function getIdtExtraMap()
    {
        return array('_' => 0, '$' => 1);
    }

    /**
     * Get map of all characters (ASCII 0-255)
     *
     * Faster than calling ord() 255 times.
     *
     * @return array ord => chr
     */
    protected function getCharMap()
    {
        return array(
            "\x0", "\x1", "\x2", "\x3", "\x4", "\x5", "\x6", "\x7", "\x8", "\x9", "\xa",
            "\xb", "\xc", "\xd", "\xe", "\xf", "\x10", "\x11", "\x12", "\x13", "\x14",
            "\x15", "\x16", "\x17", "\x18", "\x19", "\x1a", "\x1b", "\x1c", "\x1d", "\x1e",
            "\x1f", ' ', '!', '"', '#', '$', '%', '&', "\x27", '(', ')', '*', '+', ',', '-',
            '.', '/', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ':', ';', '<', '=',
            '>', '?', '@', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
            'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '[', "\x5c", ']',
            '^', '_', '`', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
            'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '{', '|', '}',
            '~', '', "\x80", "\x81", "\x82", "\x83", "\x84", "\x85", "\x86", "\x87", "\x88",
            "\x89", "\x8a", "\x8b", "\x8c", "\x8d", "\x8e", "\x8f", "\x90", "\x91", "\x92",
            "\x93", "\x94", "\x95", "\x96", "\x97", "\x98", "\x99", "\x9a", "\x9b", "\x9c",
            "\x9d", "\x9e", "\x9f", "\xa0", "\xa1", "\xa2", "\xa3", "\xa4", "\xa5", "\xa6",
            "\xa7", "\xa8", "\xa9", "\xaa", "\xab", "\xac", "\xad", "\xae", "\xaf", "\xb0",
            "\xb1", "\xb2", "\xb3", "\xb4", "\xb5", "\xb6", "\xb7", "\xb8", "\xb9", "\xba",
            "\xbb", "\xbc", "\xbd", "\xbe", "\xbf", "\xc0", "\xc1", "\xc2", "\xc3", "\xc4",
            "\xc5", "\xc6", "\xc7", "\xc8", "\xc9", "\xca", "\xcb", "\xcc", "\xcd", "\xce",
            "\xcf", "\xd0", "\xd1", "\xd2", "\xd3", "\xd4", "\xd5", "\xd6", "\xd7", "\xd8",
            "\xd9", "\xda", "\xdb", "\xdc", "\xdd", "\xde", "\xdf", "\xe0", "\xe1", "\xe2",
            "\xe3", "\xe4", "\xe5", "\xe6", "\xe7", "\xe8", "\xe9", "\xea", "\xeb", "\xec",
            "\xed", "\xee", "\xef", "\xf0", "\xf1", "\xf2", "\xf3", "\xf4", "\xf5", "\xf6",
            "\xf7", "\xf8", "\xf9", "\xfa", "\xfb", "\xfc", "\xfd", "\xfe", "\xff",
        );
    }

    /**
     * Get name of character type
     *
     * @param int $type character type (see Parser::CHAR_* constants)
     * @throws \InvalidArgumentException on invalid type
     * @return string
     */
    public function charTypeName($type)
    {
        switch ($type) {
            case static::CHAR_NONE: return 'CHAR_NONE';
            case static::CHAR_WS: return 'CHAR_WS';
            case static::CHAR_NUM: return 'CHAR_NUM';
            case static::CHAR_IDT: return 'CHAR_IDT';
            case static::CHAR_CTRL: return 'CHAR_CTRL';
            case static::CHAR_OTHER: return 'CHAR_OTHER';
            default: throw new \InvalidArgumentException('Invalid char type');
        }
    }

    /**
     * Find and return the next end of line
     * The scan will not change current state of the parser.
     *
     * @return string
     */
    public function detectEol()
    {
        $this->pushState();

        $eol = null;

        while (!$this->end) {
            if ("\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char) {
                if ("\n" === $this->char) {
                    // LF
                    $eol = "\n";
                } elseif ("\n" === $this->peek()) {
                    // CRLF
                    $eol = "\r\n";
                } else {
                    // CR
                    $eol = "\r";
                }

                break;
            } else {
                $this->shift();
            }
        }

        $this->revertState();

        return $eol;
    }

    /**
     * Get number of stored states
     *
     * @return int
     */
    public function getNumStates()
    {
        return sizeof($this->states);
    }

    /**
     * Store the current state
     *
     * Don't forget to revertState() or popState() when you are done.
     *
     * @return static
     */
    public function pushState()
    {
        $this->states[] = array($this->end, $this->i, $this->char, $this->charType, $this->lastChar, $this->line, $this->vars);

        return $this;
    }

    /**
     * Revert to the last stored state and pop it
     *
     * @throws \RuntimeException if no states are active
     * @return static
     */
    public function revertState()
    {
        $state = array_pop($this->states);
        if (null === $state) {
            throw new \RuntimeException('No states active');
        }
        list($this->end, $this->i, $this->char, $this->charType, $this->lastChar, $this->line, $this->vars) = $state;

        return $this;
    }

    /**
     * Pop the last stored state without reverting to it
     *
     * @throws \RuntimeException if no states are active
     * @return static
     */
    public function popState()
    {
        if (null === array_pop($this->states)) {
            throw new \RuntimeException('No states active');
        }

        return $this;
    }

    /**
     * Throw away all stored states
     *
     * @return static
     */
    public function clearStates()
    {
        $this->states = array();

        return $this;
    }

    /**
     * Ensure that we are at the end
     *
     * @throws ParserException if the current position is not the end
     * @return static
     */
    public function expectEnd()
    {
        if (!$this->end) {
            throw ParserException::createForCurrentState($this, 'Expected end');
        }

        return $this;
    }

    /**
     * Ensure that we are not at the end
     *
     * @throws ParserException if the current position is the end
     * @return static
     */
    public function expectNotEnd()
    {
        if ($this->end) {
            $this->unexpectedEndException();
        }

        return $this;
    }

    /**
     * Ensure that the character matches the expectation
     *
     * @param string $expectedChar expected character
     * @throws ParserException if the expectation is not met
     * @return static
     */
    public function expectChar($expectedChar)
    {
        if ($expectedChar !== $this->char) {
            $this->unexpectedCharException($expectedChar);
        }

        return $this;
    }

    /**
     * Ensure that the current character is of the given type
     *
     * @param int $expectedType the type to expect (see Parser::CHAR_* constants)
     * @throws ParserException if the expectation is not met
     * @return static
     */
    public function expectCharType($expectedType)
    {
        if ($this->charTypeMap[$this->char] !== $expectedType) {
            $this->unexpectedCharTypeException($expectedType);
        }

        return $this;
    }

    /**
     * Throw unexpected end exception
     *
     * @param string|string[]|null $expected what was expected as string or array of options, or null
     * @throws ParserException
     */
    public function unexpectedEndException($expected = null)
    {
        // prepare message
        $message = 'Unexpected end';

        // add expectations
        if (null !== $expected) {
            $message .= ', expected ' . static::formatExceptionOptions($expected);
        }

        // throw
        throw ParserException::createForCurrentState($this, $message);
    }

    /**
     * Throw unexpected character exception
     *
     * @param string|string[]|null $expected expected character as string or array of strings, or null
     * @throws ParserException
     */
    public function unexpectedCharException($expected = null)
    {
        // prepare message
        $message = 'Unexpected ';

        // add char
        if (null === $this->char) {
            $message .= 'end';
        } else {
            $message .= "\"{$this->char}\"";
        }

        // add expectations
        if (null !== $expected) {
            $message .= ', expected ' . static::formatExceptionOptions($expected);
        }

        // throw
        throw ParserException::createForCurrentState($this, $message);
    }

    /**
     * Throw unexpected character type exception
     *
     * @param int|int[]|null $expected expected character type as integer or array of integers, or null
     * @throws ParserException
     */
    public function unexpectedCharTypeException($expected = null)
    {
        // get char type
        $type = $this->charTypeMap[$this->char];

        // prepare message
        $message = 'Unexpected ' . $this->charTypeName($type);
        if (null !== $this->char && static::CHAR_OTHER !== $type) {
            $message .= " (\"{$this->char}\")";
        }

        // add expectations
        if (null !== $expected) {
            $expectedArr = (array) $expected;
            foreach ($expectedArr as &$expectedType) {
                $expectedType = $this->charTypeName($expectedType);
            }
            $message .= ', expected ' . static::formatExceptionOptions($expectedArr);
        }

        // throw
        throw ParserException::createForCurrentState($this, $message);
    }

    /**
     * Format list of options for exception messages
     *
     * @param string|string[]|null $options options as string or array of options, or null
     * @return string
     */
    public static function formatExceptionOptions($options)
    {
        // return empty string for null
        if (null === $options) {
            return '';
        }

        // format for array
        if (is_array($options)) {
            // add options
            $out = '';
            for ($i = 0, $last = sizeof($options) - 1; $i <= $last; ++$i) {

                // add delimiter
                if (0 !== $i) {
                    $out .= $last === $i ? ' or ' : ', ';
                }

                // add option
                $out .= "\"{$options[$i]}\"";

            }

            return $out;
        }

        // format for string
        return "\"{$options}\"";
    }
}
