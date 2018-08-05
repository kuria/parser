<?php declare(strict_types=1);

namespace Kuria\Parser;

use Kuria\Parser\Exception\ExceptionHelper;
use Kuria\Parser\Exception\NoActiveStatesException;
use Kuria\Parser\Exception\OutOfBoundariesException;
use Kuria\Parser\Exception\UnexpectedCharacterException;
use Kuria\Parser\Exception\UnexpectedCharacterTypeException;
use Kuria\Parser\Exception\UnexpectedEndException;
use Kuria\Parser\Exception\UnknownCharacterTypeException;

class Parser
{
    /** No character */
    const C_NONE = 1;

    /** Whitespace character */
    const C_WS = 2;

    /** Numeric character */
    const C_NUM = 3;

    /** String character */
    const C_STR = 4;

    /** Control character */
    const C_CTRL = 5;

    /** Special character */
    const C_SPECIAL = 6;

    /** Character type map */
    const CHAR_TYPE_MAP = [
        // NULL (special case)
        null => self::C_NONE,

        // ASCII
        "\x0" => self::C_CTRL,  "\x1" => self::C_CTRL,  "\x2" => self::C_CTRL,  "\x3" => self::C_CTRL,
        "\x4" => self::C_CTRL,  "\x5" => self::C_CTRL,  "\x6" => self::C_CTRL,  "\x7" => self::C_CTRL,
        "\x8" => self::C_CTRL,  "\t" => self::C_WS,     "\n" => self::C_WS,     "\v" => self::C_WS,
        "\f" => self::C_WS,     "\r" => self::C_WS,     "\xe" => self::C_CTRL,  "\xf" => self::C_CTRL,
        "\x10" => self::C_CTRL, "\x11" => self::C_CTRL, "\x12" => self::C_CTRL, "\x13" => self::C_CTRL,
        "\x14" => self::C_CTRL, "\x15" => self::C_CTRL, "\x16" => self::C_CTRL, "\x17" => self::C_CTRL,
        "\x18" => self::C_CTRL, "\x19" => self::C_CTRL, "\x1a" => self::C_CTRL, "\e" => self::C_CTRL,
        "\x1c" => self::C_CTRL, "\x1d" => self::C_CTRL, "\x1e" => self::C_CTRL, "\x1f" => self::C_CTRL,
        ' ' => self::C_WS,      '!' => self::C_SPECIAL, '"' => self::C_SPECIAL, '#' => self::C_SPECIAL,
        '$' => self::C_SPECIAL, '%' => self::C_SPECIAL, '&' => self::C_SPECIAL, '\'' => self::C_SPECIAL,
        '(' => self::C_SPECIAL, ')' => self::C_SPECIAL, '*' => self::C_SPECIAL, '+' => self::C_SPECIAL,
        ',' => self::C_SPECIAL, '-' => self::C_SPECIAL, '.' => self::C_SPECIAL, '/' => self::C_SPECIAL,
        '0' => self::C_NUM,     '1' => self::C_NUM,     '2' => self::C_NUM,     '3' => self::C_NUM,
        '4' => self::C_NUM,     '5' => self::C_NUM,     '6' => self::C_NUM,     '7' => self::C_NUM,
        '8' => self::C_NUM,     '9' => self::C_NUM,     ':' => self::C_SPECIAL, ';' => self::C_SPECIAL,
        '<' => self::C_SPECIAL, '=' => self::C_SPECIAL, '>' => self::C_SPECIAL, '?' => self::C_SPECIAL,
        '@' => self::C_SPECIAL, 'A' => self::C_STR,     'B' => self::C_STR,     'C' => self::C_STR,
        'D' => self::C_STR,     'E' => self::C_STR,     'F' => self::C_STR,     'G' => self::C_STR,
        'H' => self::C_STR,     'I' => self::C_STR,     'J' => self::C_STR,     'K' => self::C_STR,
        'L' => self::C_STR,     'M' => self::C_STR,     'N' => self::C_STR,     'O' => self::C_STR,
        'P' => self::C_STR,     'Q' => self::C_STR,     'R' => self::C_STR,     'S' => self::C_STR,
        'T' => self::C_STR,     'U' => self::C_STR,     'V' => self::C_STR,     'W' => self::C_STR,
        'X' => self::C_STR,     'Y' => self::C_STR,     'Z' => self::C_STR,     '[' => self::C_SPECIAL,
        '\\' => self::C_SPECIAL,']' => self::C_SPECIAL, '^' => self::C_SPECIAL, '_' => self::C_STR,
        '`' => self::C_SPECIAL, 'a' => self::C_STR,     'b' => self::C_STR,     'c' => self::C_STR,
        'd' => self::C_STR,     'e' => self::C_STR,     'f' => self::C_STR,     'g' => self::C_STR,
        'h' => self::C_STR,     'i' => self::C_STR,     'j' => self::C_STR,     'k' => self::C_STR,
        'l' => self::C_STR,     'm' => self::C_STR,     'n' => self::C_STR,     'o' => self::C_STR,
        'p' => self::C_STR,     'q' => self::C_STR,     'r' => self::C_STR,     's' => self::C_STR,
        't' => self::C_STR,     'u' => self::C_STR,     'v' => self::C_STR,     'w' => self::C_STR,
        'x' => self::C_STR,     'y' => self::C_STR,     'z' => self::C_STR,     '{' => self::C_SPECIAL,
        '|' => self::C_SPECIAL, '}' => self::C_SPECIAL, '~' => self::C_SPECIAL, "\x7f" => self::C_CTRL,

        // 8-bit characters
        "\x80" => self::C_STR, "\x81" => self::C_STR, "\x82" => self::C_STR, "\x83" => self::C_STR,
        "\x84" => self::C_STR, "\x85" => self::C_STR, "\x86" => self::C_STR, "\x87" => self::C_STR,
        "\x88" => self::C_STR, "\x89" => self::C_STR, "\x8a" => self::C_STR, "\x8b" => self::C_STR,
        "\x8c" => self::C_STR, "\x8d" => self::C_STR, "\x8e" => self::C_STR, "\x8f" => self::C_STR,
        "\x90" => self::C_STR, "\x91" => self::C_STR, "\x92" => self::C_STR, "\x93" => self::C_STR,
        "\x94" => self::C_STR, "\x95" => self::C_STR, "\x96" => self::C_STR, "\x97" => self::C_STR,
        "\x98" => self::C_STR, "\x99" => self::C_STR, "\x9a" => self::C_STR, "\x9b" => self::C_STR,
        "\x9c" => self::C_STR, "\x9d" => self::C_STR, "\x9e" => self::C_STR, "\x9f" => self::C_STR,
        "\xa0" => self::C_STR, "\xa1" => self::C_STR, "\xa2" => self::C_STR, "\xa3" => self::C_STR,
        "\xa4" => self::C_STR, "\xa5" => self::C_STR, "\xa6" => self::C_STR, "\xa7" => self::C_STR,
        "\xa8" => self::C_STR, "\xa9" => self::C_STR, "\xaa" => self::C_STR, "\xab" => self::C_STR,
        "\xac" => self::C_STR, "\xad" => self::C_STR, "\xae" => self::C_STR, "\xaf" => self::C_STR,
        "\xb0" => self::C_STR, "\xb1" => self::C_STR, "\xb2" => self::C_STR, "\xb3" => self::C_STR,
        "\xb4" => self::C_STR, "\xb5" => self::C_STR, "\xb6" => self::C_STR, "\xb7" => self::C_STR,
        "\xb8" => self::C_STR, "\xb9" => self::C_STR, "\xba" => self::C_STR, "\xbb" => self::C_STR,
        "\xbc" => self::C_STR, "\xbd" => self::C_STR, "\xbe" => self::C_STR, "\xbf" => self::C_STR,
        "\xc0" => self::C_STR, "\xc1" => self::C_STR, "\xc2" => self::C_STR, "\xc3" => self::C_STR,
        "\xc4" => self::C_STR, "\xc5" => self::C_STR, "\xc6" => self::C_STR, "\xc7" => self::C_STR,
        "\xc8" => self::C_STR, "\xc9" => self::C_STR, "\xca" => self::C_STR, "\xcb" => self::C_STR,
        "\xcc" => self::C_STR, "\xcd" => self::C_STR, "\xce" => self::C_STR, "\xcf" => self::C_STR,
        "\xd0" => self::C_STR, "\xd1" => self::C_STR, "\xd2" => self::C_STR, "\xd3" => self::C_STR,
        "\xd4" => self::C_STR, "\xd5" => self::C_STR, "\xd6" => self::C_STR, "\xd7" => self::C_STR,
        "\xd8" => self::C_STR, "\xd9" => self::C_STR, "\xda" => self::C_STR, "\xdb" => self::C_STR,
        "\xdc" => self::C_STR, "\xdd" => self::C_STR, "\xde" => self::C_STR, "\xdf" => self::C_STR,
        "\xe0" => self::C_STR, "\xe1" => self::C_STR, "\xe2" => self::C_STR, "\xe3" => self::C_STR,
        "\xe4" => self::C_STR, "\xe5" => self::C_STR, "\xe6" => self::C_STR, "\xe7" => self::C_STR,
        "\xe8" => self::C_STR, "\xe9" => self::C_STR, "\xea" => self::C_STR, "\xeb" => self::C_STR,
        "\xec" => self::C_STR, "\xed" => self::C_STR, "\xee" => self::C_STR, "\xef" => self::C_STR,
        "\xf0" => self::C_STR, "\xf1" => self::C_STR, "\xf2" => self::C_STR, "\xf3" => self::C_STR,
        "\xf4" => self::C_STR, "\xf5" => self::C_STR, "\xf6" => self::C_STR, "\xf7" => self::C_STR,
        "\xf8" => self::C_STR, "\xf9" => self::C_STR, "\xfa" => self::C_STR, "\xfb" => self::C_STR,
        "\xfc" => self::C_STR, "\xfd" => self::C_STR, "\xfe" => self::C_STR, "\xff" => self::C_STR,
    ];

    /** @var int current position (read-only) */
    public $i;

    /** @var string|null current character or null at the end (read-only) */
    public $char;

    /** @var string|null previous character or null at the start (read-only) */
    public $lastChar;

    /** @var int|null current line, if line tracking is enabled (read-only) */
    public $line;

    /** @var bool indicates end of input (read-only) */
    public $end;

    /** @var array user-defined variables attached to the current state */
    public $vars = [];

    /** @var string */
    protected $input;

    /** @var int */
    protected $length;

    /** @var array */
    protected $states = [];

    /** @var bool */
    protected $trackLineNumber = true;

    function __construct(string $input, bool $trackLineNumber = true)
    {
        $this->trackLineNumber = $trackLineNumber;
        $this->setInput($input);
    }

    /**
     * Get the input string
     */
    function getInput(): string
    {
        return $this->input;
    }

    /**
     * Replace the input string
     *
     * This also rewinds the parser.
     */
    function setInput(string $input): void
    {
        $this->input = $input;
        $this->length = strlen($input);
        $this->reset();
    }

    /**
     * Get input length
     */
    function getLength(): int
    {
        return $this->length;
    }

    /**
     * See if line number tracking is enabled
     */
    function isTrackingLineNumbers(): bool
    {
        return $this->trackLineNumber;
    }

    /**
     * Get type of the current character
     *
     * See Parser::C_* constants.
     */
    function type(): int
    {
        return static::CHAR_TYPE_MAP[$this->char];
    }

    /**
     * Check whether the current character is of one of the specified types
     */
    function is(int ...$types): bool
    {
        foreach ($types as $type) {
            if (static::CHAR_TYPE_MAP[$this->char] === $type) {
                return true;
            }
        }

        return false;
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
        if ($this->i < $this->length) {
            $this->char = $this->input[$this->i];
            if ($this->trackLineNumber && ($this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r")) {
                ++$this->line;
            }
        } else {
            $this->char = null;
            $this->end = true;
        }

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
        if ($this->i === 0) {
            return null;
        }

        // decrement position
        --$this->i;

        // update state
        $currentChar = $this->char;
        if ($this->trackLineNumber && ($this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r")) {
            --$this->line;
        }
        $this->char = $this->input[$this->i];
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
        if ($this->i < $this->length) {
            $this->char = $this->input[$this->i];
            if ($this->trackLineNumber && ($this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r")) {
                ++$this->line;
            }
        } else {
            $this->char = null;
            $this->end = true;
        }

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
        if ($this->i === 0) {
            return null;
        }

        // decrement position
        --$this->i;

        // update state
        if ($this->trackLineNumber && ($this->char === "\n" && $this->lastChar !== "\r" || $this->char === "\r")) {
            --$this->line;
        }
        $this->char = $this->input[$this->i];
        $this->lastChar = $this->peek(-1);
        $this->end = false;

        return $this->char;
    }

    /**
     * Get character at the given offset
     *
     * - does not affect current state.
     * - $offset is relative to the current position, unless $absolute is TRUE
     * - returns NULL if the offset is outside of the input
     */
    function peek(int $offset = 1, bool $absolute = false): ?string
    {
        $position = $absolute ? $offset : $this->i + $offset;

        return $position >= 0 && $position < $this->length ? $this->input[$position] : null;
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

        if ($position === $this->i) {
            return;
        }

        if ($position < 0 || $position > $this->length) {
            throw new OutOfBoundariesException($position);
        }

        if ($this->trackLineNumber) {
            // navigate char-by-char
            $direction = $position > $this->i ? 1 : -1;

            while ($this->i !== $position) {
                if ($direction === 1) {
                    $this->shift();
                } else {
                    $this->unshift();
                }
            }
        } else {
            // jump directly when line tracking is disabled
            $this->i = $position;

            if ($position < $this->length) {
                $this->char = $this->input[$this->i];
                $this->end = false;
            } else {
                $this->char = null;
                $this->end = true;
            }

            $this->lastChar = $this->peek(-1);
        }
    }

    /**
     * Reset states, vars and rewind to the beginning
     */
    function reset(): void
    {
        $this->states = [];
        $this->vars = [];
        $this->rewind();
    }

    /**
     * Rewind to the beginning
     */
    function rewind(): void
    {
        $this->i = 0;
        $this->end = $this->length === 0;
        $this->char = $this->end ? null : $this->input[0];
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
     * Consume specific character and return the next character
     *
     * Returns NULL if there is no next character after consumed one.
     *
     * @throws UnexpectedCharacterException if current character is not $char
     * @throws UnexpectedEndException if the parser is already at the end
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
     * Consume all characters of the specified type
     *
     * - see Parser::C_* constants
     * - position before: any
     * - position after: at first invalid character or end
     */
    function eatType(int $type): string
    {
        // scan
        $consumed = '';
        while (!$this->end) {
            // check type
            if (static::CHAR_TYPE_MAP[$this->char] !== $type) {
                break;
            }

            // consume
            $consumed .= $this->eat();
        }

        return $consumed;
    }

    /**
     * Consume all characters of the specified types
     *
     * - see Parser::C_* constants
     * - $typeMap should have types as keys and non-null values
     * - position before: any
     * - position after: at first invalid character or end
     */
    function eatTypes(array $typeMap): string
    {
        // scan
        $consumed = '';
        while (!$this->end) {
            // check type
            if (!isset($typeMap[static::CHAR_TYPE_MAP[$this->char]])) {
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
     * - position before: any
     * - position after: at first non-whitespace character, newline (if $newlines = FALSE) or end
     */
    function eatWs(bool $newlines = true): void
    {
        // scan
        while (!$this->end) {
            // check type
            if (
                static::C_WS !== static::CHAR_TYPE_MAP[$this->char]
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
     * - position before: any
     * - position after: at or after first delimiter or at end
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
     * Consume all character until end of line or input
     *
     * - returns all consumed characters
     * - if $skip is TRUE, the line-ending equence will be skipped too
     * - position before: any
     * - position after: after or at the newline
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
     * Consume end of line sequence
     *
     * - returns all consumed characters
     * - position before: at EOL
     * - position after: after EOL
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
     * Consume reamaining characters
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
     * Get chunk of the input
     *
     * - does not affect current state
     * - if the resulting chunk is out-of-bounds, only the intersected characters will be returned
     */
    function getChunk(int $start, int $end): string
    {
        if ($start >= $this->length) {
            return '';
        }

        $start = max(0, $start);

        return substr($this->input, $start, max(0, min($this->length, $end) - $start));
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
        $this->states[] = [$this->end, $this->i, $this->char, $this->lastChar, $this->line, $this->vars];
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
        [$this->end, $this->i, $this->char, $this->lastChar, $this->line, $this->vars] = $state;
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
     * Ensure that the parser is at the end
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
     * Ensure that the parser is not at the end
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
     * Ensure that the current character matches the expectation
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
     * See Parser::C_* constants.
     *
     * @throws UnexpectedCharacterTypeException if the expectation is not met
     */
    function expectCharType(int $expectedType): void
    {
        if (static::CHAR_TYPE_MAP[$this->char] !== $expectedType) {
            throw new UnexpectedCharacterTypeException(
                $this->getCharTypeName(static::CHAR_TYPE_MAP[$this->char]),
                [$this->getCharTypeName($expectedType)],
                $this->i,
                $this->line
            );
        }
    }

    /**
     * Determine character type
     *
     * @throws UnknownCharacterTypeException if the type is not known
     */
    static function getCharType(?string $char): int
    {
        if (!isset(static::CHAR_TYPE_MAP[$char])) {
            throw new UnknownCharacterTypeException(sprintf(
                'Character %s is not mapped to any type',
                ExceptionHelper::formatItem($char)
            ));
        }

        return static::CHAR_TYPE_MAP[$char];
    }

    /**
     * Get name of a character type
     *
     * See Parser::C_* constants.
     *
     * @throws UnknownCharacterTypeException if the type is not known
     */
    static function getCharTypeName(int $type): string
    {
        switch ($type) {
            case static::C_NONE:
                return 'C_NONE';
            case static::C_WS:
                return 'C_WS';
            case static::C_NUM:
                return 'C_NUM';
            case static::C_STR:
                return 'C_STR';
            case static::C_CTRL:
                return 'C_CTRL';
            case static::C_SPECIAL:
                return 'C_SPECIAL';
            default:
                throw new UnknownCharacterTypeException(sprintf('Unknown character type "%d"', $type));
        }
    }
}
