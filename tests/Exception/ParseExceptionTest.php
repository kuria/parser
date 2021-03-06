<?php declare(strict_types=1);

namespace Kuria\Parser\Exception;

use Kuria\DevMeta\Test;

class ParseExceptionTest extends Test
{
    /**
     * @dataProvider provideParseExceptions
     */
    function testShouldCreateException(
        string $className,
        array $arguments,
        string $expectedMessage,
        ?int $expectedParserPosition = null,
        ?int $expectedParserLine = null
    ) {
        /** @var ParseException $exception */
        $exception = new $className(...$arguments);

        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame($expectedParserPosition, $exception->getParserPosition());
        $this->assertSame($expectedParserLine, $exception->getParserLine());
    }

    /**
     * @dataProvider provideParseExceptions
     */
    function testShouldPropagatePreviousExceptionViaLastArgument(string $className, array $arguments)
    {
        $previousException = new \Exception('Test exception');

        /** @var ParseException $exception */
        $exception = new $className(...array_merge($arguments, [$previousException]));

        $this->assertSame($previousException, $exception->getPrevious());
    }

    function provideParseExceptions()
    {
        return [
            // className, arguments, expectedMessage, [expectedParserPosition], [expectedParserLine]
            [
                OutOfBoundariesException::class,
                [123],
                'Out of boundaries (at position 123)',
                123,
            ],
            [
                UnexpectedCharacterException::class,
                ['a', ['b', 'c'], 456, 12],
                'Unexpected "a", expected "b" or "c" on line 12 (at position 456)',
                456,
                12,
            ],
            [
                UnexpectedCharacterException::class,
                ['y', null, 2, null],
                'Unexpected "y" (at position 2)',
                2,
            ],
            [
                UnexpectedCharacterTypeException::class,
                ['FOO', ['BAR', 'BAZ', 'QUX'], 789, 24],
                'Unexpected FOO, expected BAR, BAZ or QUX on line 24 (at position 789)',
                789,
                24,
            ],
            [
                UnexpectedCharacterTypeException::class,
                ['LOREM', null, 222, null],
                'Unexpected LOREM (at position 222)',
                222,
            ],
            [
                UnexpectedEndException::class,
                [['+', '-'], 333, 48],
                'Unexpected end, expected "+" or "-" on line 48 (at position 333)',
                333,
                48,
            ],
            [
                UnexpectedEndException::class,
                [null, 444, null],
                'Unexpected end (at position 444)',
                444,
            ],
        ];
    }
}
