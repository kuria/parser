<?php declare(strict_types=1);

namespace Kuria\Parser;

use Kuria\Parser\Exception\InputException;
use Kuria\Parser\Exception\NoActiveStatesException;
use Kuria\Parser\Exception\OutOfBoundariesException;
use Kuria\Parser\Exception\ParseException;
use Kuria\Parser\Exception\UnexpectedCharacterException;
use Kuria\Parser\Exception\UnexpectedCharacterTypeException;
use Kuria\Parser\Exception\UnexpectedEndException;
use Kuria\Parser\Exception\UnknownCharacterTypeException;
use Kuria\Parser\Input\Input;
use PHPUnit\Framework\Constraint\Exception as ExceptionConstraint;
use PHPUnit\Framework\Constraint\ExceptionMessage;
use PHPUnit\Framework\TestCase;

abstract class ParserTest extends TestCase
{
    /** @var array|null */
    private $expectedParseException;

    protected function createParser(string $data = '', bool $trackLineNumber = true): Parser
    {
        return new Parser($this->createInput($data), $trackLineNumber);
    }

    abstract protected function createInput(string $data): Input;

    function testCreateFromString()
    {
        /** @var Parser|string $parserClass */
        $parserClass = get_class($this->createParser());

        $parser = $parserClass::fromString("\nhello", false);

        $this->assertInstanceOf($parserClass, $parser);
        $this->assertNull($parser->line);
        $this->assertSame("\nhello", $parser->eatRest());
    }

    function testInitialState()
    {
        $parser = $this->createParser('foo');
        $this->assertParserState($parser, 'f', null, 1, 0, false, false, []);

        $parser = $this->createParser('');
        $this->assertParserState($parser, null, null, 1, 0, false, true, []);

        $parser = $this->createParser("\nfoo");
        $this->assertParserState($parser, "\n", null, 2, 0, true, false, []);
    }

    function testGetInput()
    {
        $this->assertInstanceOf(Input::class, $this->createParser()->getInput());
    }

    function testGetLength()
    {
        $this->assertSame(0, $this->createParser('')->getLength());
        $this->assertSame(5, $this->createParser('hello')->getLength());
    }

    function testIsTrackingLineNumbers()
    {
        $this->assertTrue($this->createParser('hello', true)->isTrackingLineNumbers());
        $this->assertFalse($this->createParser('hello', false)->isTrackingLineNumbers());
    }

    function testGetCharTypeForCharacter()
    {
        $parser = $this->createParser();

        foreach ($parser::getCharTypeMap() as $char => $charType) {
            $this->assertSame(
                $charType,
                $parser->getCharType((string) $char),
                sprintf('ASCII %d should be %s',
                    ord((string) $char),
                    $parser->getCharTypeName($charType)
                )
            );
        }
    }

    function testGetCharTypeForNull()
    {
        $parser = $this->createParser();

        $this->assertSame(Parser::CHAR_NONE, $parser->getCharType(null), 'NULL should be CHAR_NONE');
    }

    /**
     * @dataProvider provideCharTypes
     */
    function testGetCharTypeName(int $type, string $expectedName)
    {
        $parser = $this->createParser();

        $this->assertSame($expectedName, $parser->getCharTypeName($type));
    }

    function provideCharTypes()
    {
        return [
            [Parser::CHAR_NONE, 'CHAR_NONE'],
            [Parser::CHAR_WS, 'CHAR_WS'],
            [Parser::CHAR_NUM, 'CHAR_NUM'],
            [Parser::CHAR_IDT, 'CHAR_IDT'],
            [Parser::CHAR_CTRL, 'CHAR_CTRL'],
            [Parser::CHAR_OTHER, 'CHAR_OTHER'],
        ];
    }

    function testExceptionOnUnknownCharType()
    {
        $parser = $this->createParser();

        $this->expectException(UnknownCharacterTypeException::class);

        $parser->getCharTypeName(12345);
    }

    /**
     * @dataProvider provideEolSamples
     */
    function testDetectEol(string $data, string $expectedEol)
    {
        $parser = $this->createParser($data);

        $this->assertSame($expectedEol, $parser->detectEol());
    }

    function provideEolSamples(): array
    {
        return [
            ["Lorem\nIpsum\nDolor\n", "\n"],
            ["Lorem\r\nIpsum\r\nDolor\r\n", "\r\n"],
            ["Lorem\rIpsum\rDolor\r", "\r"],
        ];
    }

    function testDetectEolWithoutNewline()
    {
        $parser = $this->createParser('no-newlines-here');

        $this->assertNull($parser->detectEol());
    }

    function testShift()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->shift(), 'b', 'b');
    }

    function testShiftReturnsNullAtEnd()
    {
        $parser = $this->createParser('');

        $this->assertNull($parser->shift());
    }

    function testUnshift()
    {
        $parser = $this->createParser("a\nbc");

        $parser->eat(); // eat "a"

        $this->assertParserMethodOutcome($parser, $parser->unshift(), 'a', 'a');
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);
    }

    function testUnshiftReturnsNullAtBeginning()
    {
        $parser = $this->createParser('abc');

        $this->assertNull($parser->unshift());
    }

    function testEat()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->eat(), 'a', 'b');
    }

    function testEatReturnsNullAtEnd()
    {
        $parser = $this->createParser('');

        $this->assertNull($parser->eat());
    }

    function testSpit()
    {
        $parser = $this->createParser("a\nbc");

        $parser->eat(); // eat "a"

        $this->assertParserMethodOutcome($parser, $parser->spit(), "\n", 'a');
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);
    }

    function testSpitReturnsNullAtBeginning()
    {
        $parser = $this->createParser('abc');

        $this->assertNull($parser->spit());
    }

    function testEatChar()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->eatChar('a'), 'b', 'b');
    }

    function testEatCharThrowsExceptionOnUnexpectedChar()
    {
        $parser = $this->createParser('abc');

        $this->expectException(UnexpectedCharacterException::class);
        $this->expectExceptionMessage('Unexpected "a", expected "x" on line 1 (at offset 0)');

        $parser->eatChar('x');
    }

    function testEatEol()
    {
        $parser = $this->createParser("\nx");
        $this->assertParserMethodOutcome($parser, $parser->eatEol(), "\n", 'x');

        $parser = $this->createParser("\r\nx");
        $this->assertParserMethodOutcome($parser, $parser->eatEol(), "\r\n", 'x');

        $parser = $this->createParser("\rx");
        $this->assertParserMethodOutcome($parser, $parser->eatEol(), "\r", 'x');
    }

    function testTryEatChar()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->tryEatChar('a'), true, 'b');
        $this->assertParserMethodOutcome($parser, $parser->tryEatChar('x'), false, 'b');
    }

    function testEatRest()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->eatRest(), 'abc', null);
    }

    /**
     * @dataProvider provideEatTypeSamples
     */
    function testEatType(int $type, string $data, string $expectedOutput)
    {
        $parser = $this->createParser($data);

        $this->assertParserMethodOutcome($parser, $parser->eatType($type), $expectedOutput);
    }

    function provideEatTypeSamples(): array
    {
        return [
            [Parser::CHAR_WS, '  foo', '  '],
            [Parser::CHAR_NUM, '1234x', '1234'],
            [Parser::CHAR_IDT, 'foo_bar+', 'foo_bar'],
            [Parser::CHAR_CTRL, '++foo', '++'],
            [Parser::CHAR_OTHER, chr(0) . chr(1) . 'a', chr(0) . chr(1)],
            [Parser::CHAR_NONE, 'foo', ''],
        ];
    }

    function testEatTypes()
    {
        $parser = $this->createParser('foo123bar+');

        $typeMap = [
            Parser::CHAR_IDT => 0,
            Parser::CHAR_NUM => 1,
        ];

        $this->assertParserMethodOutcome($parser, $parser->eatTypes($typeMap), 'foo123bar', '+');
    }

    function testEatUntil()
    {
        $parser = $this->createParser('abc,def;ghi');

        $this->assertParserMethodOutcome($parser, $parser->eatUntil(',', true, true), 'abc', 'd');
        $this->assertParserMethodOutcome($parser, $parser->eatUntil([',' => 0, ';' => 1], false, true), 'def', ';');
    }

    function testEatUntilWithDisallowedEnd()
    {
        $parser = $this->createParser('abc');

        $this->expectParseException(UnexpectedEndException::class, 'Unexpected end, expected "," or ";" on line 1 (at offset 3)', 3, 1);

        $parser->eatUntil([',' => 0, ';' => 1], true, false);
    }

    function testEatUntilEol()
    {
        $parser = $this->createParser("abc\r\nd\r\n");

        $this->assertParserMethodOutcome($parser, $parser->eatUntilEol(true), 'abc', 'd');
        $this->assertParserMethodOutcome($parser, $parser->eatUntilEol(false), 'd', "\r");
    }

    function testEatWs()
    {
        $parser = $this->createParser("    \na");

        $parser->eatWs(true);

        $this->assertSame('a', $parser->char);
    }

    function testEatWsNoNewlines()
    {
        $parser = $this->createParser("    \na");

        $parser->eatWs(false);

        $this->assertSame("\n", $parser->char);
    }

    function testExpectChar()
    {
        $parser = $this->createParser('a');

        $parser->expectChar('a');

        $this->addToAssertionCount(1); // no exception was thrown => ok
    }

    function testExpectCharThrowsExceptionOnUnexpectedChar()
    {
        $parser = $this->createParser('a');

        $this->expectParseException(UnexpectedCharacterException::class, 'Unexpected "a", expected "x" on line 1 (at offset 0)', 0, 1);

        $parser->expectChar('x');
    }

    function testExpectCharThrowsExceptionOnUnexpectedEnd()
    {
        $parser = $this->createParser('');

        $this->expectParseException(UnexpectedEndException::class, 'Unexpected end, expected "x" on line 1 (at offset 0)', 0, 1);

        $parser->expectChar('x');
    }

    function testExpectCharType()
    {
        $parser = $this->createParser('a');

        $parser->expectCharType(Parser::CHAR_IDT);

        $this->addToAssertionCount(1); // no exception was thrown => ok
    }

    function testExpectCharTypeThrowsExceptionOnUnexpectedChar()
    {
        $parser = $this->createParser('a');

        $this->expectParseException(UnexpectedCharacterTypeException::class, 'Unexpected CHAR_IDT, expected CHAR_NUM on line 1 (at offset 0)', 0, 1);

        $parser->expectCharType(Parser::CHAR_NUM);
    }

    function testExpectEnd()
    {
        $parser = $this->createParser('');

        $parser->expectEnd();

        $this->addToAssertionCount(1); // no exception was thrown => ok
    }

    function testExpectEndThrowsExceptionOnUnexpectedEnd()
    {
        $parser = $this->createParser('not an end');

        $this->expectParseException(UnexpectedCharacterException::class, 'Unexpected "n", expected "end" on line 1 (at offset 0)', 0, 1);

        $parser->expectEnd();
    }

    function testExpectNotEnd()
    {
        $parser = $this->createParser('not an end');

        $parser->expectNotEnd();

        $this->addToAssertionCount(1); // no exception was thrown => ok
    }

    function testExpectNotEndThrowsExceptionOnUnexpectedEnd()
    {
        $parser = $this->createParser('');

        $this->expectParseException(UnexpectedEndException::class, 'Unexpected end on line 1 (at offset 0)', 0, 1);

        $parser->expectNotEnd();
    }

    function testSeekForward()
    {
        $parser = $this->createParser("abc\ndef");

        $parser->seek(1);
        $this->assertParserState($parser, 'b', 'a', 1, 1, false, false);

        $parser->seek(3);
        $this->assertParserState($parser, 'd', "\n", 2, 4, false, false);

        $parser->seek(3);
        $this->assertParserState($parser, null, 'f', 2, 7, false, true);
    }

    function testSeekBackward()
    {
        $parser = $this->createParser("abc\ndef");

        $parser->eatRest();
        $this->assertParserState($parser, null, 'f', 2, 7, false, true);

        $parser->seek(-1);
        $this->assertParserState($parser, 'f', 'e', 2, 6, false, false);

        $parser->seek(-4);
        $this->assertParserState($parser, 'c', 'b', 1, 2, false, false);
    }

    function testSeekMaintainsCorrectLineNumber()
    {
        $parser = $this->createParser("a\nb\nc\nd");

        $parser->seek(4);
        $this->assertSame(3, $parser->line);

        $parser->seek(-4);
        $this->assertSame(1, $parser->line);
    }

    function testSeekZeroOffsetDoesNothing()
    {
        $parser = $this->createParser('baz');

        $parser->eat();

        $this->assertParserState($parser, 'a', 'b', 1, 1, false, false);
        $parser->seek(0);
        $this->assertParserState($parser, 'a', 'b', 1, 1, false, false);
    }

    function testSeekThrowsExceptionIfOutOfBoundsPositive()
    {
        $parser = $this->createParser('abc');

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at offset 100)', 100);

        $parser->seek(100);
    }

    function testSeekThrowsExceptionIfOutOfBoundsNegative()
    {
        $parser = $this->createParser('abc');

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at offset -100)', -100);

        $parser->seek(-100);
    }

    function testSeekAbsolute()
    {
        $parser = $this->createParser('baz');

        $parser->eat();

        $parser->seek(2, true);
        $this->assertParserState($parser, 'z', 'a', 1, 2, false, false);

        $parser->seek(0, true);
        $this->assertParserState($parser, 'b', null, 1, 0, false, false);
    }

    function testSeekAbsoluteThrowsExceptionIfOutOfBoundsPositive()
    {
        $parser = $this->createParser('abc');

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at offset 100)', 100);

        $parser->seek(100, true);
    }
    
    function testSeekAbsoluteThrowsExceptionIfOutOfBoundsNegative()
    {
        $parser = $this->createParser('abc');

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at offset -100)', -100);

        $parser->seek(-100, true);
    }

    function testJumpForward()
    {
        $parser = $this->createParser("abc\ndef", false);

        $parser->seek(1);
        $this->assertParserState($parser, 'b', 'a', null, 1, false, false);

        $parser->seek(3);
        $this->assertParserState($parser, 'd', "\n", null, 4, false, false);

        $parser->seek(3);
        $this->assertParserState($parser, null, 'f', null, 7, false, true);
    }

    function testJumpBackward()
    {
        $parser = $this->createParser("abc\ndef", false);

        $parser->eatRest();
        $this->assertParserState($parser, null, 'f', null, 7, false, true);

        $parser->seek(-1);
        $this->assertParserState($parser, 'f', 'e', null, 6, false, false);

        $parser->seek(-3);
        $this->assertParserState($parser, "\n", 'c', null, 3, true, false);
    }

    function testJumpIgnoresLineNumber()
    {
        $parser = $this->createParser("a\nb\nc\nd", false);

        $parser->seek(4);
        $this->assertNull($parser->line);

        $parser->seek(-4);
        $this->assertNull($parser->line);
    }

    function testJumpZeroOffsetDoesNothing()
    {
        $parser = $this->createParser('baz', false);

        $parser->eat();

        $this->assertParserState($parser, 'a', 'b', null, 1, false, false);
        $parser->seek(0);
        $this->assertParserState($parser, 'a', 'b', null, 1, false, false);
    }

    function testJumpThrowsExceptionIfOutOfBoundsPositive()
    {
        $parser = $this->createParser('abc', false);

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at offset 100)', 100);

        $parser->seek(100);
    }

    function testJumpThrowsExceptionIfOutOfBoundsNegative()
    {
        $parser = $this->createParser('abc', false);

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at offset -100)', -100);

        $parser->seek(-100);
    }

    function testJumpAbsolute()
    {
        $parser = $this->createParser('baz', false);

        $parser->eat();

        $parser->seek(2, true);
        $this->assertParserState($parser, 'z', 'a', null, 2, false, false);

        $parser->seek(0, true);
        $this->assertParserState($parser, 'b', null, null, 0, false, false);
    }

    function testJumpAbsoluteThrowsExceptionIfOutOfBoundsPositive()
    {
        $parser = $this->createParser('abc', false);

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at offset 100)', 100);

        $parser->seek(100, true);
    }

    function testJumpAbsoluteThrowsExceptionIfOutOfBoundsNegative()
    {
        $parser = $this->createParser('abc', false);

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at offset -100)', -100);

        $parser->seek(-100, true);
    }

    function testRewind()
    {
        $parser = $this->createParser("a\nabc");

        $parser->eat();
        $parser->rewind();

        $this->assertParserState($parser, 'a', null, 1, 0, false, false);
    }

    function testReset()
    {
        $parser = $this->createParser("a\nabc");

        $parser->eat();
        $parser->pushState();
        $parser->reset();

        $this->assertParserState($parser, 'a', null, 1, 0, false, false);
        $this->assertSame(0, $parser->countStates());
    }

    function testPeek()
    {
        $parser = $this->createParser('abc');

        $this->assertSame(null, $parser->peek(-1));
        $this->assertSame('a', $parser->peek(0));
        $this->assertSame('b', $parser->peek(1));
        $this->assertSame('c', $parser->peek(2));
        $this->assertSame(null, $parser->peek(3));
    }

    function testChunk()
    {
        $parser = $this->createParser('aaaaabbbbbcccccx');

        // chunking should just load data and do not affect parser state
        $this->assertSame('aaaaa', $parser->getChunk(0, 5));
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);

        $this->assertSame('aaaab', $parser->getChunk(1, 5));
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);

        $this->assertSame('bccccc', $parser->getChunk(9, 6));
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);

        $this->assertSame('bccccc', $parser->getChunk(9, 6));
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);

        // chunks beyond available range should contain all available data
        $this->assertSame('x', $parser->getChunk(15, 10));

        // chunking past available range should yield an empty chunk
        $this->assertSame('', $parser->getChunk(100, 5));
    }

    function testChunkThrowsExceptionOnNegativePosition()
    {
        $this->expectException(InputException::class);

        $this->createParser('Hello world')->getChunk(-1, 5);
    }

    function testChunkThrowsExceptionOnZeroLength()
    {
        $this->expectException(InputException::class);

        $this->createParser('Hello world')->getChunk(0, 0);
    }

    function testChunkThrowsExceptionOnNegativeLength()
    {
        $this->expectException(InputException::class);

        $this->createParser('Hello world')->getChunk(0, -1);
    }

    function testStates()
    {
        $parser = $this->createParser("Lorem\nIpsum\nDolor\n");

        $this->assertSame(0, $parser->countStates());

        $parser->seek(6);
        $this->assertParserState($parser, 'I', "\n", 2, 6, false, false, []);

        $parser->pushState();
        $this->assertSame(1, $parser->countStates());

        $parser->eatRest();
        $parser->vars['foo'] = 'bar';
        $this->assertParserState($parser, null, "\n", 4, 18, false, true, ['foo' => 'bar']);

        $parser->pushState();
        $this->assertSame(2, $parser->countStates());

        $parser->rewind();
        unset($parser->vars['foo']);
        $charTypeAtEnd = $parser->charType;
        $this->assertParserState($parser, 'L', null, 1, 0, false, false, []);

        $parser->revertState();
        $this->assertParserState($parser, null, "\n", 4, 18, false, true, ['foo' => 'bar']);
        $this->assertSame(1, $parser->countStates());
        $this->assertNotSame($charTypeAtEnd, $parser->charType);

        $parser->revertState();
        $this->assertParserState($parser, 'I', "\n", 2, 6, false, false, []);
        $this->assertSame(0, $parser->countStates());

        $parser->pushState();
        $parser->eat();
        $parser->popState();
        $this->assertParserState($parser, 'p', 'I', 2, 7, false, false, []);
    }

    function testRevertStateThrowsExceptionIfNoStates()
    {
        $parser = $this->createParser();

        $this->expectException(NoActiveStatesException::class);

        $parser->revertState();
    }

    function testPopStateThrowsExceptionIfNoStates()
    {
        $parser = $this->createParser();

        $this->expectException(NoActiveStatesException::class);

        $parser->popState();
    }

    function testClearStates()
    {
        $parser = $this->createParser();

        $parser->pushState();
        $parser->clearStates();

        $this->assertSame(0, $parser->countStates());
    }
    
    function testLineTrackingDisabled()
    {
        $parser = $this->createParser("foo\nbar", false);

        $this->assertNull($parser->line);
        $parser->eatRest();
        $this->assertNull($parser->line);
    }

    /**
     * Assert that result of some operation on the parser matches the expected outcome
     */
    protected function assertParserMethodOutcome(
        Parser $parser,
        $actualResult,
        $expectedResult,
        ?string $expectedCurrentChar = null
    ): void {
        $this->assertSame($expectedResult, $actualResult, 'Actual and expected result must match');

        if (func_num_args() >= 4) {
            $this->assertSame($expectedCurrentChar, $parser->char, sprintf('Expected current character to be "%s"', $expectedCurrentChar));
        }
    }

    /**
     * Assert that the state of the parser matches the exepectations
     *
     * @param Parser      $parser
     * @param string|null $expectedChar
     * @param string|null $expectedLastChar
     * @param int|null    $expectedLine
     * @param int         $expectedOffset
     * @param bool        $expectedAtNewline
     * @param bool        $expectedEnd
     * @param array|null  $expectedVars
     */
    protected function assertParserState(
        Parser $parser,
        ?string $expectedChar,
        ?string $expectedLastChar,
        ?int $expectedLine,
        int $expectedOffset,
        bool $expectedAtNewline,
        bool $expectedEnd,
        ?array $expectedVars = null
    ): void {
        $this->assertSame($expectedChar, $parser->char, sprintf('Expected current character to be "%s"', $expectedChar));
        $this->assertSame($expectedLastChar, $parser->lastChar, sprintf('Expected last character to be "%s"', $expectedLastChar));
        $this->assertSame($expectedLine, $parser->line, sprintf('Expected current line to be %d', $expectedLine));
        $this->assertSame($expectedOffset, $parser->i, sprintf('Expected current offset to be %d', $expectedOffset));
        $this->assertSame($expectedAtNewline, $parser->atNewline(), sprintf('Expected atNewline() to yield %s', $expectedAtNewline ? 'true' : 'false'));
        $this->assertSame($expectedEnd, $parser->end, sprintf('Expected end to be %s', $expectedEnd ? 'true' : 'false'));

        if ($expectedVars !== null) {
            $this->assertSame($expectedVars, $parser->vars, 'Expected vars to match');
        }
    }

    protected function expectParseException(string $class, string $message, int $expectedOffset, ?int $expectedLine = null): void
    {
        $this->expectedParseException = [
            'class' => $class,
            'message' => $message,
            'offset' => $expectedOffset,
            'line' => $expectedLine,
        ];
    }

    protected function runTest()
    {
        $e = null;
        try {
            return parent::runTest();
        } catch (ParseException $e) {
            if ($this->expectedParseException) {
                // check expected parse exception
                $this->assertThat($e, new ExceptionConstraint($this->expectedParseException['class']));
                $this->assertThat($e, new ExceptionMessage($this->expectedParseException['message']));
                $this->assertSame($this->expectedParseException['offset'], $e->getParserOffset());
                $this->assertSame($this->expectedParseException['line'], $e->getParserLine());
            } else {
                throw $e;
            }
        } finally {
            if ($e === null && $this->expectedParseException) {
                $this->assertThat(null, new ExceptionConstraint($this->expectedParseException['class']));
            }
        }
    }
}
