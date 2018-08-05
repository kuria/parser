<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

abstract class ExceptionHelper
{
    static function formatItem($item, bool $quote = true): string
    {
        $out = '';

        if ($quote) {
            $out .= '"';
        }

        $out .= addcslashes((string) $item, "\000..\037");

        if ($quote) {
            $out .= '"';
        }

        return $out;
    }

    static function formatList(?array $items, bool $quote = true): ?string
    {
        if (empty($items)) {
            return null;
        }

        $out = '';

        for ($i = 0, $last = count($items) - 1; $i <= $last; ++$i) {
            // append delimiter
            if ($i > 0) {
                $out .= $last === $i ? ' or ' : ', ';
            }

            // append item
            $out .= static::formatItem($items[$i], $quote);
        }

        return $out;
    }
}
