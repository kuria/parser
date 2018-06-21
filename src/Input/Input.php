<?php declare(strict_types=1);

namespace Kuria\Parser\Input;

use Kuria\Parser\Exception\InputException;

/**
 * Input abstraction
 *
 * Usage example:
 *
 *  for ($i = 0; isset($input->data[$i - $input->offset]) || $input->loadData($i); ++$i) {
 *      echo $input->data[$i - $input->offset];
 *  }
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
     */
    abstract function getTotalLength(): ?int;

    /**
     * Load data for the given position
     */
    abstract function loadData(int $position): bool;

    /**
     * Get chunk of the data
     *
     * @throws InputException if the position or length is invalid
     */
    function getChunk(int $position, int $length): string
    {
        if ($position < 0) {
            throw new InputException('Position cannot be less than 0');
        }
        if ($length < 1) {
            throw new InputException('Length cannot be less than 1');
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
