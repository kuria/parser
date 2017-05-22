<?php

namespace Kuria\Parser\Input;

/**
 * Input
 *
 * Usage example:
 *
 * // read the entire input char-by-char
 * for ($i = 0; isset($input->data[$i - $input->offset]) || $input->loadData($i); ++$i) {
 *     echo $input->data[$i - $input->offset];
 * }
 *
 * @author ShiraNai7 <shira.cz>
 */
abstract class Input
{
    /** @var string the data */
    public $data = '';
    /** @var int offset of the data */
    public $offset = 0;
    /** @var int length of the data */
    public $length = 0;

    /**
     * Get total number of bytes available, if known
     *
     * @return int|null
     */
    abstract public function getTotalLength();

    /**
     * Load data for the given position
     *
     * @param int $position
     * @return bool
     */
    abstract public function loadData($position);

    /**
     * Get chunk of the data
     *
     * @param int $position absolute starting position (>= 0)
     * @param int $length   up to N bytes will be read (>= 1)
     * @throws \InvalidArgumentException if the position or length is invalid
     * @return string
     */
    public function chunk($position, $length)
    {
        if ($position < 0) {
            throw new \InvalidArgumentException('Invalid position');
        }
        if ($length < 1) {
            throw new \InvalidArgumentException('Invalid length');
        }

        $chunk = '';
        $currentPosition = $position;
        $remainingChars = $length;

        while ($remainingChars > 0 && (isset($this->data[$currentPosition - $this->offset]) || $this->loadData($currentPosition))) {
            $inputPosition = $currentPosition - $this->offset;
            $availableChars = $this->length - $inputPosition;
            $charsToRead = $availableChars >= $remainingChars ? $remainingChars : $availableChars;

            $chunk .= substr($this->data, $inputPosition, $charsToRead);
            
            $remainingChars -= $charsToRead;
            $currentPosition += $charsToRead;
        }

        return $chunk;
    }
}
