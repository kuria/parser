<?php declare(strict_types=1);

namespace Kuria\Parser;

use Kuria\Parser\Exception\InputException;
use Kuria\Parser\Exception\NoActiveStatesException;
use Kuria\Parser\Exception\OutOfBoundariesException;
use Kuria\Parser\Exception\UnexpectedCharacterException;
use Kuria\Parser\Exception\UnexpectedCharacterTypeException;
use Kuria\Parser\Exception\UnexpectedEndException;
use Kuria\Parser\Exception\UnknownCharacterTypeException;
use Kuria\Parser\Input\Input;
use Kuria\Parser\Input\MemoryInput;

class Parser
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

    /** @var array|null [class => [char1 => type1, ...], ...] */
    protected static $charTypeMap;

    /** @var int current index */
    public $i;
    /** @var string|null current character or null on string end */
    public $char;
    /** @var int type of the current character */
    public $charType;
    /** @var string|null previous character (null on start) */
    public $lastChar;
    /** @var int|null current line, if line tracking is enabled (newline at the current position has already been counted) */
    public $line;
    /** @var bool end of input */
    public $end;
    /** @var array generic variables attached to current state */
    public $vars = [];

    /** @var Input */
    protected $input;
    /** @var array stored states */
    protected $states = [];
    /** @var bool */
    protected $trackLineNumber = true;

    function __construct(Input $input, bool $trackLineNumber = true)
    {
        if (!isset(static::$charTypeMap[static::class])) {
            $this->initializeCharTypeMap();
        }

        $this->input = $input;
        $this->trackLineNumber = $trackLineNumber;

        $this->rewind();
    }

    /**
     * Create parser for the given string
     *
     * @return static
     */
    static function fromString(string $data, bool $trackLineNumber = true)
    {
        return new static(new MemoryInput($data), $trackLineNumber);
    }

    function getInput(): Input
    {
        return $this->input;
    }

    /**
     * Get length, if known
     */
    function getLength(): ?int
    {
        return $this->input->getTotalLength();
    }

    /**
     * See if line number tracking is enabled
     */
    function isTrackingLineNumbers(): bool
    {
        return $this->trackLineNumber;
    }

    /**
     * Go to the next character and return the current one
     *
     * Returns NULL at the end.
     */
    function eat(): ?string
    {
        // implementation is almost identical to shift()
        // but is copy-pasted for performance reasons

        // ended?
        if ($this->end) {
            return null;
        }

        // increment position
        ++$this->i;

        // update state
        $this->lastChar = $this->char;
        if (isset($this->input->data[$this->i - $this->input->offset]) || $this->input->loadData($this->i)) {
            $this->char = $this->input->data[$this->i - $this->input->offset];
            if ($this->trackLineNumber && ($this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r")) {
                ++$this->line;
            }
        } else {
            $this->char = null;
            $this->end = true;
        }
        $this->charType = static::$charTypeMap[static::class][$this->char];

        return $this->lastChar;
    }

    /**
     * Go to the previous character and return the current one
     *
     * Returns NULL at the beginning.
     */
    function spit(): ?string
    {
        // implementation is almost identical to unshift()
        // but is copy-pasted for performance reasons

        // at the beginning?
        if ($this->i <= 0) {
            return null;
        }

        // calculate new position and check data
        $newPosition = $this->i - 1;
        if (!isset($this->input->data[$newPosition - $this->input->offset]) && !$this->input->loadData($newPosition)) {
            throw new InputException('Failed to load previous data');
        }

        // set new position
        $this->i = $newPosition;

        // update state
        $currentChar = $this->char;
        if ($this->trackLineNumber && ($this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r")) {
            --$this->line;
        }
        $this->char = $this->input->data[$this->i - $this->input->offset];
        $this->charType = static::$charTypeMap[static::class][$this->char];
        $this->lastChar = $this->peek(-1);
        $this->end = false;

        return $currentChar;
    }

    /**
     * Go to the next character and return it
     *
     * Returns NULL at the end.
     */
    function shift(): ?string
    {
        // implementation is almost identical to eat()
        // but is copy-pasted for performance reasons

        // ended?
        if ($this->end) {
            return null;
        }

        // increment position
        ++$this->i;

        // update state
        $this->lastChar = $this->char;
        if (isset($this->input->data[$this->i - $this->input->offset]) || $this->input->loadData($this->i)) {
            $this->char = $this->input->data[$this->i - $this->input->offset];
            if ($this->trackLineNumber && ($this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r")) {
                ++$this->line;
            }
        } else {
            $this->char = null;
            $this->end = true;
        }
        $this->charType = static::$charTypeMap[static::class][$this->char];

        return $this->char;
    }

    /**
     * Go to the previous character and return it
     *
     * Returns NULL at the beginning.
     */
    function unshift(): ?string
    {
        // implementation is almost identical to spit()
        // but is copy-pasted for performance reasons

        // at the beginning?
        if ($this->i <= 0) {
            return null;
        }

        // calculate new position and check data
        $newPosition = $this->i - 1;
        if (!isset($this->input->data[$newPosition - $this->input->offset]) && !$this->input->loadData($newPosition)) {
            throw new InputException('Failed to load previous data');
        }

        // set new position
        $this->i = $newPosition;

        // update state
        if ($this->trackLineNumber && ($this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r")) {
            --$this->line;
        }
        $this->char = $this->input->data[$this->i - $this->input->offset];
        $this->charType = static::$charTypeMap[static::class][$this->char];
        $this->lastChar = $this->peek(-1);
        $this->end = false;

        return $this->char;
    }

    /**
     * Get character at the given offset
     *
     * - does not affect current state.
     * - $offset is relative to the current position, unless $absolute is TRUE
     * - returns NULL if the offset is outside of the data
     */
    function peek(int $offset = 1, bool $absolute = false): ?string
    {
        $position = $absolute ? $offset : $this->i + $offset;

        if (
            $position >= 0
            && (
                isset($this->input->data[$position - $this->input->offset])
                || $this->input->loadData($position)
            )
        ) {
            return $this->input->data[$position - $this->input->offset];
        }

        return null;
    }

    /**
     * Get chunk of input data
     *
     * - does not affect current state
     * - $position is absolute starting position (>= 0)
     * - $length specifies how many bytes to read (>= 1)
     *
     * @throws InputException if the position or length is invalid
     */
    function getChunk(int $position, int $length): string
    {
        return $this->input->getChunk($position, $length);
    }

    /**
     * Alter current position
     *
     * $offset is relative to the current position, unless $absolute is TRUE.
     *
     * @throws OutOfBoundariesException when navigating beyond available boundaries
     */
    function seek(int $offset, bool $absolute = false): void
    {
        if ($offset === 0) {
            if ($absolute) {
                $this->rewind();
            }

            return;
        }

        $position = $absolute ? $offset : $this->i + $offset;

        if ($position < 0) {
            throw new OutOfBoundariesException($position);
        }

        if ($this->trackLineNumber || !$this->jump($position)) {
            $direction = $position > $this->i ? 1 : -1;

            while ($this->i !== $position) {
                if ($direction === 1) {
                    if ($this->shift() === null && $this->i !== $position) {
                        throw new OutOfBoundariesException($position);
                    }
                } else {
                    $this->unshift();
                }
            }
        }
    }

    /**
     * Try to jump to the specified position
     *
     * - internal; only safe to use with line tracking disabled
     * - returns FALSE on failure
     *
     * @throws OutOfBoundariesException if the position is invalid
     */
    protected function jump(int $position): bool
    {
        if (($length = $this->input->getTotalLength()) !== null) {
            if ($position < 0 || $position > $length) {
                throw new OutOfBoundariesException($position);
            }

            // update state
            $this->i = $position - $this->input->offset;

            if (isset($this->input->data[$position - $this->input->offset]) || $this->input->loadData($position)) {
                $this->char = $this->input->data[$this->i];
                $this->end = false;
            } else {
                $this->char = null;
                $this->end = true;
            }
            $this->charType = static::$charTypeMap[static::class][$this->char];
            $this->lastChar = $this->peek(-1);

            return true;
        }

        // cannot jump safely if the length is not known
        return false;
    }

    /**
     * Reset state
     */
    function reset(): void
    {
        $this->states = [];
        $this->rewind();
    }

    /**
     * Rewind to the beginning
     */
    function rewind(): void
    {
        $this->i = 0;
        $this->end = !$this->input->loadData(0);
        $this->char = $this->end ? null : $this->input->data[0];
        $this->charType = static::$charTypeMap[static::class][$this->char];
        $this->lastChar = null;

        if ($this->trackLineNumber) {
            $this->line = 1;
            if ($this->char === "\n") {
                ++$this->line;
            }
        } else {
            $this->line = null;
        }
    }

    /**
     * See if the parser is at the start of a newline sequence
     *
     * Internally, the logic from this function is copy-pasted
     * inline for performance reasons.
     */
    function atNewline(): bool
    {
        return $this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r";
    }

    /**
     * Consume specific character and return the next character
     *
     * Returns NULL at the end.
     *
     * @throws UnexpectedCharacterException if current character is not $char
     * @throws UnexpectedEndException if at the end
     */
    function eatChar(string $char): ?string
    {
        if ($char !== $this->char) {
            if ($this->char === null) {
                throw new UnexpectedEndException([$char], $this->i, $this->line);
            }

            throw new UnexpectedCharacterException($this->char, [$char], $this->i, $this->line);
        }

        return $this->shift();
    }

    /**
     * Attempt to consume specific character and return success state
     */
    function tryEatChar(string $char): bool
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
     * - see Parser::CHAR_* constants
     * - pre-offset: any
     * - post-offset: at first invalid character or end
     */
    function eatType(int $type): string
    {
        // scan
        $consumed = '';
        while (!$this->end) {
            // check type
            if (static::$charTypeMap[static::class][$this->char] !== $type) {
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
     * - see Parser::CHAR_* constants
     * - $typeMap should have types as keys and non-null values
     * - pre-offset: any
     * - post-offset: at first invalid character or end
     */
    function eatTypes(array $typeMap): string
    {
        // scan
        $consumed = '';
        while (!$this->end) {
            // check type
            if (!isset($typeMap[static::$charTypeMap[static::class][$this->char]])) {
                break;
            }

            // consume
            $consumed .= $this->eat();
        }

        return $consumed;
    }

    /**
     * Consume whitespace, if any
     *
     * - if $newlines is TRUE, newline characters will be consumed too
     * - pre-offset: any
     * - post-offset: at first non-whitespace character, newline (if $newlines = FALSE) or end
     */
    function eatWs(bool $newlines = true): void
    {
        // scan
        while (!$this->end) {
            // check type
            if (
                static::CHAR_WS !== static::$charTypeMap[static::class][$this->char]
                || !$newlines && ($this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r")
            ) {
                break;
            }

            // shift
            $this->shift();
        }
    }

    /**
     * Consume all characters until the specified delimiters
     *
     * - returns all consumed characters
     * - $delimiterMap should be a single character or an array with delimiter characters as keys and non-null values
     * - pre-offset: any
     * - post-offset: at or after first delimiter or at end
     *
     * @throws UnexpectedEndException if end is encountered and $allowEnd is FALSE
     */
    function eatUntil($delimiterMap, bool $skipDelimiter = true, bool $allowEnd = false): string
    {
        if (!is_array($delimiterMap)) {
            $delimiterMap = [$delimiterMap => true];
        }

        // scan
        $consumed = '';
        while (!$this->end && !isset($delimiterMap[$this->char])) {
            $consumed .= $this->eat();
        }

        // check end
        if ($this->end && !$allowEnd) {
            throw new UnexpectedEndException(array_keys($delimiterMap), $this->i, $this->line);
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
     * - returns all consumed characters
     * - if $skip is TRUE, the line-ending equence will be skipped too
     * - pre-offset: any
     * - post-offset: after or at the newline
     */
    function eatUntilEol(bool $skip = true): string
    {
        $consumed = '';
        while (!$this->end && !($this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r")) {
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
     * - returns all consumed characters
     * - pre-offset: at EOL
     * - post-offset: after EOL
     */
    function eatEol(): string
    {
        $out = '';

        while (
            !$this->end
            && (
                ($this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r")
                || $this->char === "\n" && $this->lastChar === "\r"
            )
        ) {
            $out .= $this->eat();
        }

        return $out;
    }

    /**
     * Eat all reamaining characters
     *
     * Returns all consumed characters.
     */
    function eatRest(): string
    {
        $out = '';

        while (!$this->end) {
            $out .= $this->eat();
        }

        return $out;
    }

    /**
     * Get character type
     */
    function getCharType(?string $char): int
    {
        return static::$charTypeMap[static::class][$char];
    }

    /**
     * Get name of character type
     *
     * See Parser::CHAR_* constants.
     *
     * @throws UnknownCharacterTypeException on unknown type
     */
    function getCharTypeName(int $type): string
    {
        switch ($type) {
            case static::CHAR_NONE:
                return 'CHAR_NONE';
            case static::CHAR_WS:
                return 'CHAR_WS';
            case static::CHAR_NUM:
                return 'CHAR_NUM';
            case static::CHAR_IDT:
                return 'CHAR_IDT';
            case static::CHAR_CTRL:
                return 'CHAR_CTRL';
            case static::CHAR_OTHER:
                return 'CHAR_OTHER';
            default:
                throw new UnknownCharacterTypeException(sprintf('Unknown character type "%d"', $type));
        }
    }

    /**
     * Find and return the next end of line sequence
     *
     * The scan will not change current state of the parser.
     */
    function detectEol(): ?string
    {
        $this->pushState();

        try {
            $eol = null;

            while (!$this->end) {
                if ($this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r") {
                    if ($this->char === "\n") {
                        // LF
                        $eol = "\n";
                    } elseif ($this->peek() === "\n") {
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

            return $eol;
        } finally {
            $this->revertState();
        }
    }

    /**
     * Get number of stored states
     */
    function countStates(): int
    {
        return count($this->states);
    }

    /**
     * Store the current state
     *
     * @see Parser::revertState()
     * @see Parser::popState()
     */
    function pushState(): void
    {
        $this->states[] = [$this->end, $this->i, $this->char, $this->charType, $this->lastChar, $this->line, $this->vars];
    }

    /**
     * Revert to the last stored state and pop it
     *
     * @throws NoActiveStatesException if no states are active
     */
    function revertState(): void
    {
        $state = array_pop($this->states);
        if ($state === null) {
            throw new NoActiveStatesException('Cannot revert state - no active states');
        }
        [$this->end, $this->i, $this->char, $this->charType, $this->lastChar, $this->line, $this->vars] = $state;
    }

    /**
     * Pop the last stored state without reverting to it
     *
     * @throws NoActiveStatesException if no states are active
     */
    function popState(): void
    {
        if (array_pop($this->states) === null) {
            throw new NoActiveStatesException('Cannot pop state - no active states');
        }
    }

    /**
     * Throw away all stored states
     */
    function clearStates(): void
    {
        $this->states = [];
    }

    /**
     * Ensure that we are at the end
     *
     * @throws UnexpectedCharacterException if the current position is not the end
     */
    function expectEnd(): void
    {
        if ($this->char !== null) {
            throw new UnexpectedCharacterException($this->char, ['end'], $this->i, $this->line);
        }
    }

    /**
     * Ensure that we are not at the end
     *
     * @throws UnexpectedEndException if the current position is the end
     */
    function expectNotEnd(): void
    {
        if ($this->end) {
            throw new UnexpectedEndException(null, $this->i, $this->line);
        }
    }

    /**
     * Ensure that the character matches the expectation
     *
     * @throws UnexpectedEndException if at the end
     * @throws UnexpectedCharacterException if the expectation is not met
     */
    function expectChar(string $expectedChar): void
    {
        if ($expectedChar !== $this->char) {
            throw $this->char === null
                ? new UnexpectedEndException([$expectedChar], $this->i, $this->line)
                : new UnexpectedCharacterException($this->char, [$expectedChar], $this->i, $this->line);
        }
    }

    /**
     * Ensure that the current character is of the given type
     *
     * See Parser::CHAR_* constants.
     *
     * @throws UnexpectedCharacterTypeException if the expectation is not met
     */
    function expectCharType(int $expectedType): void
    {
        if (static::$charTypeMap[static::class][$this->char] !== $expectedType) {
            throw new UnexpectedCharacterTypeException(
                $this->getCharTypeName(static::$charTypeMap[static::class][$this->char]),
                [$this->getCharTypeName($expectedType)],
                $this->i,
                $this->line
            );
        }
    }

    /**
     * Get character => character type map
     */
    static function getCharTypeMap(): array
    {
        if (!isset(static::$charTypeMap[static::class])) {
            static::initializeCharTypeMap();
        }

        return static::$charTypeMap[static::class];
    }

    /**
     * Initialize character type map
     */
    protected static function initializeCharTypeMap(): void
    {
        $map = [
            '' => static::CHAR_NONE, // special case for NULL
        ];

        $wsMap = static::getWhitespaceMap();
        $idtExtraMap = static::getExtraIdtCharMap();

        foreach (static::getAsciiCharMap() as $ord => $char) {
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

            $map[$char] = $type;
        }

        static::$charTypeMap[static::class] = $map;
    }

    /**
     * Get map of characters which are considered whitespace
     */
    protected static function getWhitespaceMap(): array
    {
        return [' ' => 0, "\n" => 1, "\r" => 2, "\t" => 3, "\h" => 4];
    }

    /**
     * Get map of extra characters (beyond a-z, A-Z) that are considered identifier characters
     */
    protected static function getExtraIdtCharMap(): array
    {
        return ['_' => 0];
    }

    /**
     * Get ord => chr map of all characters (ASCII 0-255)
     */
    protected static function getAsciiCharMap(): array
    {
        return [
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
        ];
    }
}
