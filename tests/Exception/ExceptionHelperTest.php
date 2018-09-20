<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

use Kuria\DevMeta\Test;

class ExceptionHelperTest extends Test
{
    /**
     * @dataProvider provideItems
     */
    function testShouldFormatItem($item, bool $quote, string $expectedOutput)
    {
        $this->assertSame($expectedOutput, ExceptionHelper::formatItem($item, $quote));
    }

    function provideItems(): array
    {
        // item, quote, expectedOutput
        return [
            ['foo', true, '"foo"'],
            [123, true, '"123"'],
            ['bar', false, 'bar'],
            [456, false, '456'],
            ["baz\r\nqux", true, '"baz\r\nqux"'],
            ["\0quux\equuz\t", false, '\000quux\033quuz\t'],
        ];
    }

    /**
     * @dataProvider provideLists
     */
    function testShouldFormatList(?array $items, bool $quote, ?string $expectedOutput)
    {
        $this->assertSame($expectedOutput, ExceptionHelper::formatList($items, $quote));
    }

    function provideLists(): array
    {
        return [
            // items, quote, expectedOutput
            [null, true, null],
            [[], true, null],
            [['foo'], true, '"foo"'],
            [[123], false, '123'],
            [['bar', 456], true, '"bar" or "456"'],
            [['baz', 'qux', 789], false, 'baz, qux or 789'],
            [['a', 'b', "quux\nquuz\0"], true, '"a", "b" or "quux\nquuz\000"'],
        ];
    }
}
