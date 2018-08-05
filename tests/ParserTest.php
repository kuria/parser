<?php declare(strict_types=1);

namespace Kuria\Parser;

use Kuria\Parser\Exception\NoActiveStatesException;
use Kuria\Parser\Exception\OutOfBoundariesException;
use Kuria\Parser\Exception\ParseException;
use Kuria\Parser\Exception\UnexpectedCharacterException;
use Kuria\Parser\Exception\UnexpectedCharacterTypeException;
use Kuria\Parser\Exception\UnexpectedEndException;
use Kuria\Parser\Exception\UnknownCharacterTypeException;
use PHPUnit\Framework\Constraint\Exception as ExceptionConstraint;
use PHPUnit\Framework\Constraint\ExceptionMessage;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    /** @var array|null */
    private $expectedParseException;

    function testShouldIntializeState()
    {
        $parser = $this->createParser('foo');
        $this->assertParserState($parser, 'f', null, 1, 0, false, false, []);

        $parser = $this->createParser('');
        $this->assertParserState($parser, null, null, 1, 0, false, true, []);

        $parser = $this->createParser("\nfoo");
        $this->assertParserState($parser, "\n", null, 2, 0, true, false, []);
    }

    function testShouldGetInput()
    {
        $this->assertSame('foo bar', $this->createParser('foo bar')->getInput());
    }

    function testShouldReplaceInput()
    {
        $parser = $this->createParser("baz\nqux");

        $parser->eatRest();
        $parser->vars['foo'] = 'bar';

        $this->assertSame("baz\nqux", $parser->getInput());
        $this->assertParserState($parser, null, 'x', 2, 7, false, true, ['foo' => 'bar']);

        $parser->setInput('quux quuz');

        $this->assertSame('quux quuz', $parser->getInput());
        $this->assertParserState($parser, 'q', null, 1, 0, false, false, []);
    }

    function testShouldGetLength()
    {
        $this->assertSame(0, $this->createParser('')->getLength());
        $this->assertSame(5, $this->createParser('hello')->getLength());
    }

    function testShouldConfigureLineTracking()
    {
        $this->assertTrue($this->createParser('hello', true)->isTrackingLineNumbers());
        $this->assertFalse($this->createParser('hello', false)->isTrackingLineNumbers());
    }

    function testShouldNotTrackLinesIfDisabled()
    {
        $parser = $this->createParser("foo\nbar", false);

        $this->assertNull($parser->line);
        $parser->eatRest();
        $this->assertNull($parser->line);
    }

    function testShouldGetCurrentCharType()
    {
        $parser = new Parser(implode(array_keys(Parser::CHAR_TYPE_MAP)));

        while (!$parser->end) {
            $this->assertSame(
                Parser::CHAR_TYPE_MAP[$parser->char],
                $parser->type(),
                sprintf(
                    'Byte %d should be %s',
                    $parser->i,
                    Parser::getCharTypeName(Parser::CHAR_TYPE_MAP[$parser->char])
                )
            );

            $parser->eat();
        }

        $this->assertSame(256, $parser->i);
    }

    function testShouldCheckCurrentCharType()
    {
        $parser = new Parser(implode(array_keys(Parser::CHAR_TYPE_MAP)));

        while (!$parser->end) {
            $this->assertTrue($parser->is($parser->type()));

            $parser->eat();
        }

        $this->assertSame(256, $parser->i);
    }

    function testShouldCheckCurretCharTypeAgainstMultipleOptions()
    {
        $parser = new Parser('5');

        $this->assertTrue($parser->is(Parser::C_STR, Parser::C_NUM));
        $this->assertFalse($parser->is(Parser::C_STR, Parser::C_SPECIAL));
    }

    function testShouldEat()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->eat(), 'a', 'b');
    }

    function testEatShouldReturnNullAtEnd()
    {
        $parser = $this->createParser('');

        $this->assertNull($parser->eat());
    }

    function testShouldSpit()
    {
        $parser = $this->createParser("a\nbc");

        $parser->eat(); // eat "a"

        $this->assertParserMethodOutcome($parser, $parser->spit(), "\n", 'a');
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);
    }

    function testSpitShouldReturnNullAtBeginning()
    {
        $parser = $this->createParser('abc');

        $this->assertNull($parser->spit());
    }

    function testShouldShift()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->shift(), 'b', 'b');
    }

    function testShiftShouldReturnNullAtEnd()
    {
        $parser = $this->createParser('');

        $this->assertNull($parser->shift());
    }

    function testShouldUnshift()
    {
        $parser = $this->createParser("a\nbc");

        $parser->eat(); // eat "a"

        $this->assertParserMethodOutcome($parser, $parser->unshift(), 'a', 'a');
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);
    }

    function testUnshiftShouldReturnNullAtBeginning()
    {
        $parser = $this->createParser('abc');

        $this->assertNull($parser->unshift());
    }

    function testShouldPeek()
    {
        $parser = $this->createParser('abc');

        $this->assertNull($parser->peek(-1));
        $this->assertSame('a', $parser->peek(0));
        $this->assertSame('b', $parser->peek(1));
        $this->assertSame('c', $parser->peek(2));
        $this->assertNull($parser->peek(3));
    }

    function testShouldSeekForward()
    {
        $parser = $this->createParser("abc\ndef");

        $parser->seek(1);
        $this->assertParserState($parser, 'b', 'a', 1, 1, false, false);

        $parser->seek(3);
        $this->assertParserState($parser, 'd', "\n", 2, 4, false, false);

        $parser->seek(3);
        $this->assertParserState($parser, null, 'f', 2, 7, false, true);
    }

    function testShouldSeekBackward()
    {
        $parser = $this->createParser("abc\ndef");

        $parser->eatRest();
        $this->assertParserState($parser, null, 'f', 2, 7, false, true);

        $parser->seek(-1);
        $this->assertParserState($parser, 'f', 'e', 2, 6, false, false);

        $parser->seek(-4);
        $this->assertParserState($parser, 'c', 'b', 1, 2, false, false);
    }

    function testSeekShouldMaintainCorrectLineNumber()
    {
        $parser = $this->createParser("a\nb\nc\nd");

        $parser->seek(4);
        $this->assertSame(3, $parser->line);

        $parser->seek(-4);
        $this->assertSame(1, $parser->line);
    }

    function testSeekWithZeroOffsetShouldDoNothing()
    {
        $parser = $this->createParser('baz');

        $parser->eat();

        $this->assertParserState($parser, 'a', 'b', 1, 1, false, false);
        $parser->seek(0);
        $this->assertParserState($parser, 'a', 'b', 1, 1, false, false);
    }

    function testSeekToIdenticalPositionShouldDoNothing()
    {
        $parser = $this->createParser('baz');

        $parser->eat();

        $this->assertParserState($parser, 'a', 'b', 1, 1, false, false);
        $parser->seek(1, true);
        $this->assertParserState($parser, 'a', 'b', 1, 1, false, false);
    }

    function testSeekShouldThrowExceptionIfOutOfBoundsPositive()
    {
        $parser = $this->createParser('abc');

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at position 100)', 100);

        $parser->seek(100);
    }

    function testSeekShouldThrowExceptionIfOutOfBoundsNegative()
    {
        $parser = $this->createParser('abc');

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at position -100)', -100);

        $parser->seek(-100);
    }

    function testShouldSeekAbsolute()
    {
        $parser = $this->createParser('baz');

        $parser->eat();

        $parser->seek(2, true);
        $this->assertParserState($parser, 'z', 'a', 1, 2, false, false);

        $parser->seek(0, true);
        $this->assertParserState($parser, 'b', null, 1, 0, false, false);
    }

    function testSeekAbsoluteShouldThrowExceptionIfOutOfBoundsPositive()
    {
        $parser = $this->createParser('abc');

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at position 100)', 100);

        $parser->seek(100, true);
    }

    function testSeekAbsoluteShouldThrowExceptionIfOutOfBoundsNegative()
    {
        $parser = $this->createParser('abc');

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at position -100)', -100);

        $parser->seek(-100, true);
    }

    function testShouldJumpForward()
    {
        $parser = $this->createParser("abc\ndef", false);

        $parser->seek(1);
        $this->assertParserState($parser, 'b', 'a', null, 1, false, false);

        $parser->seek(3);
        $this->assertParserState($parser, 'd', "\n", null, 4, false, false);

        $parser->seek(3);
        $this->assertParserState($parser, null, 'f', null, 7, false, true);
    }

    function testShouldJumpBackward()
    {
        $parser = $this->createParser("abc\ndef", false);

        $parser->eatRest();
        $this->assertParserState($parser, null, 'f', null, 7, false, true);

        $parser->seek(-1);
        $this->assertParserState($parser, 'f', 'e', null, 6, false, false);

        $parser->seek(-3);
        $this->assertParserState($parser, "\n", 'c', null, 3, true, false);
    }

    function testJumpShouldIgnoreLineNumber()
    {
        $parser = $this->createParser("a\nb\nc\nd", false);

        $parser->seek(4);
        $this->assertNull($parser->line);

        $parser->seek(-4);
        $this->assertNull($parser->line);
    }

    function testJumpWithZeroOffsetShouldDoNothing()
    {
        $parser = $this->createParser('baz', false);

        $parser->eat();

        $this->assertParserState($parser, 'a', 'b', null, 1, false, false);
        $parser->seek(0);
        $this->assertParserState($parser, 'a', 'b', null, 1, false, false);
    }

    function testJumpShouldThrowExceptionIfOutOfBoundsPositive()
    {
        $parser = $this->createParser('abc', false);

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at position 100)', 100);

        $parser->seek(100);
    }

    function testJumpShouldThrowExceptionIfOutOfBoundsNegative()
    {
        $parser = $this->createParser('abc', false);

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at position -100)', -100);

        $parser->seek(-100);
    }

    function testShouldJumpAbsolute()
    {
        $parser = $this->createParser('baz', false);

        $parser->eat();

        $parser->seek(2, true);
        $this->assertParserState($parser, 'z', 'a', null, 2, false, false);

        $parser->seek(0, true);
        $this->assertParserState($parser, 'b', null, null, 0, false, false);
    }

    function testJumpAbsoluteShouldThrowExceptionIfOutOfBoundsPositive()
    {
        $parser = $this->createParser('abc', false);

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at position 100)', 100);

        $parser->seek(100, true);
    }

    function testJumpAbsoluteShouldThrowExceptionIfOutOfBoundsNegative()
    {
        $parser = $this->createParser('abc', false);

        $this->expectParseException(OutOfBoundariesException::class, 'Out of boundaries (at position -100)', -100);

        $parser->seek(-100, true);
    }

    function testShouldReset()
    {
        $parser = $this->createParser("a\nabc");

        $parser->eat();
        $parser->pushState();
        $parser->vars['foo'] = 'bar';
        $parser->reset();

        $this->assertParserState($parser, 'a', null, 1, 0, false, false, []);
        $this->assertSame(0, $parser->countStates());
    }

    function testShouldRewind()
    {
        $parser = $this->createParser("a\nabc");

        $parser->eat();
        $parser->rewind();

        $this->assertParserState($parser, 'a', null, 1, 0, false, false);
    }

    function testShouldEatChar()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->eatChar('a'), 'b', 'b');
    }

    function testEatCharShouldThrowExceptionOnUnexpectedChar()
    {
        $parser = $this->createParser('abc');

        $this->expectException(UnexpectedCharacterException::class);
        $this->expectExceptionMessage('Unexpected "a", expected "x" on line 1 (at position 0)');

        $parser->eatChar('x');
    }

    function testEatCharShouldThrowExceptionAtEnd()
    {
        $parser = $this->createParser();

        $this->expectException(UnexpectedEndException::class);
        $this->expectExceptionMessage('Unexpected end, expected "x" on line 1 (at position 0)');

        $parser->eatChar('x');
    }

    function testShouldTryEatChar()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->tryEatChar('a'), true, 'b');
        $this->assertParserMethodOutcome($parser, $parser->tryEatChar('x'), false, 'b');
    }

    /**
     * @dataProvider provideEatTypeSamples
     */
    function testShouldEatType(int $type, string $input, string $expectedOutput)
    {
        $parser = $this->createParser($input);

        $this->assertParserMethodOutcome($parser, $parser->eatType($type), $expectedOutput);
    }

    function provideEatTypeSamples(): array
    {
        return [
            // type, input, expectedOutput
            [Parser::C_WS, '  foo', '  '],
            [Parser::C_NUM, '1234x', '1234'],
            [Parser::C_STR, 'foo_bar+', 'foo_bar'],
            [Parser::C_SPECIAL, '++foo', '++'],
            [Parser::C_CTRL, chr(0) . chr(1) . 'a', chr(0) . chr(1)],
            [Parser::C_NONE, 'foo', ''],
        ];
    }

    function testShouldEatTypes()
    {
        $parser = $this->createParser('foo123bar+');

        $typeMap = [
            Parser::C_STR => 0,
            Parser::C_NUM => 1,
        ];

        $this->assertParserMethodOutcome($parser, $parser->eatTypes($typeMap), 'foo123bar', '+');
    }

    function testShouldEatWs()
    {
        $parser = $this->createParser("    \na");

        $parser->eatWs(true);

        $this->assertSame('a', $parser->char);
    }

    function testShouldEatWsWithoutNewlines()
    {
        $parser = $this->createParser("    \na");

        $parser->eatWs(false);

        $this->assertSame("\n", $parser->char);
    }

    function testShouldEatUntil()
    {
        $parser = $this->createParser('abc,def;ghi');

        $this->assertParserMethodOutcome($parser, $parser->eatUntil(',', true, true), 'abc', 'd');
        $this->assertParserMethodOutcome($parser, $parser->eatUntil([',' => 0, ';' => 1], false, true), 'def', ';');
    }

    function testEatUntilShouldThrowExceptionAtEndIfEndIsNotAllowed()
    {
        $parser = $this->createParser('abc');

        $this->expectParseException(UnexpectedEndException::class, 'Unexpected end, expected "," or ";" on line 1 (at position 3)', 3, 1);

        $parser->eatUntil([',' => 0, ';' => 1], true, false);
    }

    function testShouldEatUntilEol()
    {
        $parser = $this->createParser("abc\r\nd\r\n");

        $this->assertParserMethodOutcome($parser, $parser->eatUntilEol(true), 'abc', 'd');
        $this->assertParserMethodOutcome($parser, $parser->eatUntilEol(false), 'd', "\r");
    }

    function testShouldEatEol()
    {
        $parser = $this->createParser("\nx");
        $this->assertParserMethodOutcome($parser, $parser->eatEol(), "\n", 'x');

        $parser = $this->createParser("\r\nx");
        $this->assertParserMethodOutcome($parser, $parser->eatEol(), "\r\n", 'x');

        $parser = $this->createParser("\rx");
        $this->assertParserMethodOutcome($parser, $parser->eatEol(), "\r", 'x');
    }

    function testShouldEatRest()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->eatRest(), 'abc', null);
    }

    function testShouldGetChunk()
    {
        $parser = $this->createParser('0123456789abcdef');

        // getting a chunk should not affect parser state
        $this->assertSame('01234', $parser->getChunk(0, 5));
        $this->assertParserState($parser, '0', null, 1, 0, false, false);

        $this->assertSame('12345', $parser->getChunk(1, 6));
        $this->assertParserState($parser, '0', null, 1, 0, false, false);

        $this->assertSame('9abcde', $parser->getChunk(9, 15));
        $this->assertParserState($parser, '0', null, 1, 0, false, false);

        $this->assertSame('9abcde', $parser->getChunk(9, 15));
        $this->assertParserState($parser, '0', null, 1, 0, false, false);

        // getting a partial or out-of-bounds chunk should return the available chars
        $this->assertSame('f', $parser->getChunk(15, 100));
        $this->assertSame('0', $parser->getChunk(-10, 1));
        $this->assertSame('', $parser->getChunk(100, 105));
        $this->assertSame('', $parser->getChunk(-105, -100));
        $this->assertSame('', $parser->getChunk(3, 0));
        $this->assertSame('', $parser->getChunk(0, 0));
        $this->assertSame('', $parser->getChunk(1, 1));
    }

    /**
     * @dataProvider provideEolSamples
     */
    function testShouldDetectEol(string $input, string $expectedEol)
    {
        $parser = $this->createParser($input);

        $this->assertSame($expectedEol, $parser->detectEol());
    }

    function provideEolSamples(): array
    {
        return [
            // input, expectedEol
            ["Lorem\nIpsum\nDolor\n", "\n"],
            ["Lorem\r\nIpsum\r\nDolor\r\n", "\r\n"],
            ["Lorem\rIpsum\rDolor\r", "\r"],
        ];
    }

    function testShouldDetectEolWithoutNewline()
    {
        $parser = $this->createParser('no-newlines-here');

        $this->assertNull($parser->detectEol());
    }

    function testShouldManageStates()
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
        $this->assertParserState($parser, 'L', null, 1, 0, false, false, []);

        $parser->revertState();
        $this->assertParserState($parser, null, "\n", 4, 18, false, true, ['foo' => 'bar']);
        $this->assertSame(1, $parser->countStates());

        $parser->revertState();
        $this->assertParserState($parser, 'I', "\n", 2, 6, false, false, []);
        $this->assertSame(0, $parser->countStates());

        $parser->pushState();
        $parser->eat();
        $parser->popState();
        $this->assertParserState($parser, 'p', 'I', 2, 7, false, false, []);
    }

    function testRevertStateShouldThrowExceptionIfNoStatesExist()
    {
        $parser = $this->createParser();

        $this->expectException(NoActiveStatesException::class);

        $parser->revertState();
    }

    function testPopStateShouldThrowExceptionIfNoStatesExist()
    {
        $parser = $this->createParser();

        $this->expectException(NoActiveStatesException::class);

        $parser->popState();
    }

    function testShouldClearStates()
    {
        $parser = $this->createParser();

        $parser->pushState();
        $parser->clearStates();

        $this->assertSame(0, $parser->countStates());
    }

    function testShouldExpectEnd()
    {
        $parser = $this->createParser('');

        $parser->expectEnd();

        $this->addToAssertionCount(1); // no exception was thrown => ok
    }

    function testExpectEndShouldThrowExceptionOnUnexpectedEnd()
    {
        $parser = $this->createParser('not an end');

        $this->expectParseException(UnexpectedCharacterException::class, 'Unexpected "n", expected "end" on line 1 (at position 0)', 0, 1);

        $parser->expectEnd();
    }

    function testShouldExpectNotEnd()
    {
        $parser = $this->createParser('not an end');

        $parser->expectNotEnd();

        $this->addToAssertionCount(1); // no exception was thrown => ok
    }

    function testExpectNotEndShouldThrowExceptionOnUnexpectedEnd()
    {
        $parser = $this->createParser('');

        $this->expectParseException(UnexpectedEndException::class, 'Unexpected end on line 1 (at position 0)', 0, 1);

        $parser->expectNotEnd();
    }

    function testShouldExpectChar()
    {
        $parser = $this->createParser('a');

        $parser->expectChar('a');

        $this->addToAssertionCount(1); // no exception was thrown => ok
    }

    function testExpectCharShouldThrowExceptionOnUnexpectedChar()
    {
        $parser = $this->createParser('a');

        $this->expectParseException(UnexpectedCharacterException::class, 'Unexpected "a", expected "x" on line 1 (at position 0)', 0, 1);

        $parser->expectChar('x');
    }

    function testExpectCharShouldThrowExceptionOnUnexpectedEnd()
    {
        $parser = $this->createParser('');

        $this->expectParseException(UnexpectedEndException::class, 'Unexpected end, expected "x" on line 1 (at position 0)', 0, 1);

        $parser->expectChar('x');
    }

    function testShouldExpectCharType()
    {
        $parser = $this->createParser('a');

        $parser->expectCharType(Parser::C_STR);

        $this->addToAssertionCount(1); // no exception was thrown => ok
    }

    function testExpectCharTypeShouldThrowExceptionOnUnexpectedChar()
    {
        $parser = $this->createParser('a');

        $this->expectParseException(UnexpectedCharacterTypeException::class, 'Unexpected C_STR, expected C_NUM on line 1 (at position 0)', 0, 1);

        $parser->expectCharType(Parser::C_NUM);
    }

    function testShouldGetCharType()
    {
        foreach (Parser::CHAR_TYPE_MAP as $char => $charType) {
            $this->assertSame(
                $charType,
                Parser::getCharType((string) $char),
                sprintf(
                    'Byte %d should be %s',
                    ord((string) $char),
                    Parser::getCharTypeName($charType)
                )
            );
        }

        $this->assertSame(Parser::C_NONE, Parser::getCharType(null), 'NULL should be C_NONE');
    }
    function testShouldThrowExceptionWhenGettingCharTypeForString()
    {
        $this->expectException(UnknownCharacterTypeException::class);
        $this->expectExceptionMessage('Character "foo" is not mapped to any type');

        Parser::getCharType('foo');
    }

    /**
     * @dataProvider provideCharTypes
     */
    function testShouldGetCharTypeName(int $type, string $expectedName)
    {
        $this->assertSame($expectedName, Parser::getCharTypeName($type));
    }

    function provideCharTypes()
    {
        return [
            // type, expectedName
            [Parser::C_NONE, 'C_NONE'],
            [Parser::C_WS, 'C_WS'],
            [Parser::C_NUM, 'C_NUM'],
            [Parser::C_STR, 'C_STR'],
            [Parser::C_CTRL, 'C_CTRL'],
            [Parser::C_SPECIAL, 'C_SPECIAL'],
        ];
    }

    function testShouldThrowExceptionOnUnknownCharType()
    {
        $this->expectException(UnknownCharacterTypeException::class);

        Parser::getCharTypeName(12345);
    }

    private function createParser(string $input = '', bool $trackLineNumber = true): Parser
    {
        return new Parser($input, $trackLineNumber);
    }

    /**
     * Assert that result of some operation on the parser matches the expected outcome
     */
    private function assertParserMethodOutcome(
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
     * @param int         $expectedPosition
     * @param bool        $expectedAtNewline
     * @param bool        $expectedEnd
     * @param array|null  $expectedVars
     */
    private function assertParserState(
        Parser $parser,
        ?string $expectedChar,
        ?string $expectedLastChar,
        ?int $expectedLine,
        int $expectedPosition,
        bool $expectedAtNewline,
        bool $expectedEnd,
        ?array $expectedVars = null
    ): void {
        $this->assertSame(
            $expectedChar,
            $parser->char,
            sprintf('Expected current character to be "%s"', $expectedChar)
        );
        $this->assertSame(
            $expectedLastChar,
            $parser->lastChar,
            sprintf('Expected last character to be "%s"', $expectedLastChar)
        );
        $this->assertSame(
            $expectedLine,
            $parser->line,
            sprintf('Expected current line to be %d', $expectedLine)
        );
        $this->assertSame(
            $expectedPosition,
            $parser->i,
            sprintf('Expected current position to be %d', $expectedPosition)
        );
        $this->assertSame(
            $expectedAtNewline,
            $parser->atNewline(),
            sprintf('Expected atNewline() to yield %s', $expectedAtNewline ? 'true' : 'false')
        );
        $this->assertSame(
            $expectedEnd,
            $parser->end,
            sprintf('Expected end to be %s', $expectedEnd ? 'true' : 'false')
        );

        if ($expectedVars !== null) {
            $this->assertSame($expectedVars, $parser->vars, 'Expected vars to match');
        }
    }

    private function expectParseException(string $class, string $message, int $expectedPosition, ?int $expectedLine = null): void
    {
        $this->expectedParseException = [
            'class' => $class,
            'message' => $message,
            'position' => $expectedPosition,
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
                $this->assertSame($this->expectedParseException['position'], $e->getParserPosition());
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
