<?php

namespace Kuria\Parser;

use Kuria\Parser\Input\Input;
use Kuria\Parser\Input\MemoryInput;

/**
 * Input parser
 *
 * @author ShiraNai7 <shira.cz>
 */
class InputParser extends Parser
{
    /** @var Input */
    protected $input;

    /**
     * @param Input $input           the input
     * @param bool  $trackLineNumber track line number 1/0
     */
    public function __construct(Input $input, $trackLineNumber = true)
    {
        parent::__construct();

        // set vars
        $this->input = $input;
        $this->trackLineNumber = $trackLineNumber;

        // initial state
        $this->rewind();
    }

    /**
     * Create the parser for a given string
     *
     * @param string $data            the string to parse
     * @param bool   $trackLineNumber track line number 1/0
     * @return static
     */
    public static function fromString($data, $trackLineNumber = true)
    {
        return new static(new MemoryInput($data), $trackLineNumber);
    }

    /**
     * Get the input
     *
     * @return Input
     */
    public function getInput()
    {
        return $this->input;
    }

    public function getLength()
    {
        return $this->input->getTotalLength();
    }

    protected function jump($position)
    {
        if (null !== ($length = $this->input->getTotalLength())) {
            if ($position < 0 || $position > $length) {
                throw new ParserException(sprintf('Cannot jump to position "%d" - out of boundaries', $position));
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
            $this->charType = $this->charTypeMap[$this->char];
            $this->lastChar = $this->peek(-1);

            return true;
        }

        // cannot jump safely if the length is not known
        return false;
    }

    public function eat()
    {
        // implementation is almost identical to shift()
        // but is copy-pasted for performance reasons

        // ended?
        if ($this->end) {
            return;
        }

        // increment position
        ++$this->i;

        // update state
        $this->lastChar = $this->char;
        if (isset($this->input->data[$this->i - $this->input->offset]) || $this->input->loadData($this->i)) {
            $this->char = $this->input->data[$this->i - $this->input->offset];
            if ($this->trackLineNumber && ("\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char)) {
                ++$this->line;
            }
        } else {
            $this->char = null;
            $this->end = true;
        }
        $this->charType = $this->charTypeMap[$this->char];

        return $this->lastChar;
    }

    public function spit()
    {
        // implementation is almost identical to unshift()
        // but is copy-pasted for performance reasons

        // at the beginning?
        if ($this->i <= 0) {
            return;
        }

        // calculate new position and check data
        $newPosition = $this->i - 1;
        if (!isset($this->input->data[$newPosition - $this->input->offset]) && !$this->input->loadData($newPosition)) {
            throw new ParserException('Failed to load the previous data');
        }

        // set new position
        $this->i = $newPosition;

        // update state
        $currentChar = $this->char;
        if ($this->trackLineNumber && ("\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char)) {
            --$this->line;
        }
        $this->char = $this->input->data[$this->i - $this->input->offset];
        $this->charType = $this->charTypeMap[$this->char];
        $this->lastChar = $this->peek(-1);
        $this->end = false;

        return $currentChar;
    }

    public function shift()
    {
        // implementation is almost identical to eat()
        // but is copy-pasted for performance reasons

        // ended?
        if ($this->end) {
            return;
        }

        // increment position
        ++$this->i;

        // update state
        $this->lastChar = $this->char;
        if (isset($this->input->data[$this->i - $this->input->offset]) || $this->input->loadData($this->i)) {
            $this->char = $this->input->data[$this->i - $this->input->offset];
            if ($this->trackLineNumber && ("\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char)) {
                ++$this->line;
            }
        } else {
            $this->char = null;
            $this->end = true;
        }
        $this->charType = $this->charTypeMap[$this->char];

        return $this->char;
    }

    public function unshift()
    {
        // implementation is almost identical to spit()
        // but is copy-pasted for performance reasons
        
        // at the beginning?
        if ($this->i <= 0) {
            return;
        }

        // calculate new position and check data
        $newPosition = $this->i - 1;
        if (!isset($this->input->data[$newPosition - $this->input->offset]) && !$this->input->loadData($newPosition)) {
            throw new ParserException('Failed to load the previous data');
        }

        // set new position
        $this->i = $newPosition;

        // update state
        if ($this->trackLineNumber && ("\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char)) {
            --$this->line;
        }
        $this->char = $this->input->data[$this->i - $this->input->offset];
        $this->charType = $this->charTypeMap[$this->char];
        $this->lastChar = $this->peek(-1);
        $this->end = false;

        return $this->char;
    }

    public function peek($offset = 1, $absolute = false)
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
    }

    public function rewind()
    {
        $this->i = 0;
        $this->end = !$this->input->loadData(0);
        $this->char = $this->end ? null : $this->input->data[0];
        $this->charType = $this->charTypeMap[$this->char];
        $this->lastChar = null;
        if ($this->trackLineNumber) {
            $this->line = 1;
            if ("\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char) {
                ++$this->line;
            }
        } else {
            $this->line = null;
        }

        return $this;
    }

    public function chunk($position, $length)
    {
        return $this->input->chunk($position, $length);
    }
}
