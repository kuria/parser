<?php

namespace Kuria\Parser;

abstract class ParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Create parser instance for given input
     *
     * @param string $data
     * @param bool   $trackLineNumber
     * @return Parser
     */
    abstract protected function createParser($data = '', $trackLineNumber = true);

    public function testIsTrackingLineNumbers()
    {
        $this->assertTrue($this->createParser('hello', true)->isTrackingLineNumbers());
        $this->assertFalse($this->createParser('hello', false)->isTrackingLineNumbers());
    }

    public function testGetLength()
    {
        $this->assertSame(0, $this->createParser('')->getLength());
        $this->assertSame(5, $this->createParser('hello')->getLength());
    }

    public function testInitialState()
    {
        $parser = $this->createParser('foo');
        $this->assertParserState($parser, 'f', null, 1, 0, false, false, array());

        $parser = $this->createParser('');
        $this->assertParserState($parser, null, null, 1, 0, false, true, array());

        $parser = $this->createParser("\nfoo");
        $this->assertParserState($parser, "\n", null, 2, 0, true, false, array());
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage Unexpected end
     */
    public function testUnexpectedEndException()
    {
        $parser = $this->createParser();

        $parser->unexpectedEndException();
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage Unexpected
     */
    public function testUnexpectedCharException()
    {
        $parser = $this->createParser();

        $parser->unexpectedCharException();
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage Unexpected CHAR_
     */
    public function testUnexpectedCharTypeException()
    {
        $parser = $this->createParser();

        $parser->unexpectedCharTypeException();
    }

    public function testFormatExceptionOptions()
    {
        $this->assertSame('', Parser::formatExceptionOptions(null));
        $this->assertSame('"foo"', Parser::formatExceptionOptions('foo'));
        $this->assertSame('"foo"', Parser::formatExceptionOptions(array('foo')));
        $this->assertSame('"foo" or "bar"', Parser::formatExceptionOptions(array('foo', 'bar')));
        $this->assertSame('"foo", "bar" or "baz"', Parser::formatExceptionOptions(array('foo', 'bar', 'baz')));
    }

    public function testCharTypeGiven()
    {
        $parser = $this->createParser();

        foreach ($parser->charTypeMap as $char => $charType) {
            $this->assertSame(
                $charType,
                $parser->charType($char),
                sprintf('ASCII %d should be %s',
                    ord($char),
                    $parser->charTypeName($charType)
                )
            );
        }
    }

    public function testCharTypeCurrent()
    {
        // generate input from all possible characters
        $input = '';
        foreach (array_keys($this->createParser()->charTypeMap) as $char) {
            $input .= $char;
        }
        $inputLength = strlen($input);
        
        // iterate the input manually and assert the parser's state
        $parser = $this->createParser($input);
        for ($i = 0; $i < $inputLength; ++$i) {
            $this->assertSame($i, $parser->i);
            $this->assertSame(
                $parser->charTypeMap[$input[$i]],
                $parser->charType(),
                sprintf(
                    'ASCII %d should be %s',
                    ord($parser->char),
                    $parser->charTypeName($parser->charTypeMap[$input[$i]])
                )
            );
            $this->assertSame(
                $parser->charTypeMap[$input[$i]],
                $parser->charType,
                sprintf(
                    'current character should be %s',
                    $parser->charTypeName($parser->charTypeMap[$input[$i]])
                )
            );

            $parser->shift();
        }
    }

    public function testCharNone()
    {
        $parser = $this->createParser();

        $this->assertSame(Parser::CHAR_NONE, $parser->charType(null), 'null should be CHAR_NONE');
    }

    public function provideCharTypes()
    {
        return array(
            array(Parser::CHAR_NONE, 'CHAR_NONE'),
            array(Parser::CHAR_WS, 'CHAR_WS'),
            array(Parser::CHAR_NUM, 'CHAR_NUM'),
            array(Parser::CHAR_IDT, 'CHAR_IDT'),
            array(Parser::CHAR_CTRL, 'CHAR_CTRL'),
            array(Parser::CHAR_OTHER, 'CHAR_OTHER'),
        );
    }

    /**
     * @dataProvider provideCharTypes
     * @param int $type
     * @param string $expectedName
     */
    public function testCharTypeName($type, $expectedName)
    {
        $parser = $this->createParser();

        $this->assertSame($expectedName, $parser->charTypeName($type));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCharTypeNameInvalidThrowsException()
    {
        $parser = $this->createParser();

        $parser->charTypeName(12345);
    }

    public function provideEolSamples()
    {
        return array(
            array("Lorem\nIpsum\nDolor\n", "\n"),
            array("Lorem\r\nIpsum\r\nDolor\r\n", "\r\n"),
            array("Lorem\rIpsum\rDolor\r", "\r"),
        );
    }

    /**
     * @dataProvider provideEolSamples
     * @param string $testString
     * @param string $expectedEol
     */
    public function testDetectEol($testString, $expectedEol)
    {
        $parser = $this->createParser($testString);

        $this->assertSame($expectedEol, $parser->detectEol());
    }

    public function testDetectEolNoNewline()
    {
        $parser = $this->createParser('no-newlines-here');

        $this->assertNull($parser->detectEol());
    }

    public function testShift()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->shift(), 'b', 'b');
    }

    public function testShiftReturnsNullAtEnd()
    {
        $parser = $this->createParser('');

        $this->assertNull($parser->shift());
    }

    public function testUnshift()
    {
        $parser = $this->createParser("a\nbc");

        $parser->eat(); // eat "a"

        $this->assertParserMethodOutcome($parser, $parser->unshift(), 'a', 'a');
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);
    }

    public function testUnshiftReturnsNullAtBeginning()
    {
        $parser = $this->createParser('abc');

        $this->assertNull($parser->unshift());
    }

    public function testEat()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->eat(), 'a', 'b');
    }

    public function testEatReturnsNullAtEnd()
    {
        $parser = $this->createParser('');

        $this->assertNull($parser->eat());
    }

    public function testSpit()
    {
        $parser = $this->createParser("a\nbc");

        $parser->eat(); // eat "a"

        $this->assertParserMethodOutcome($parser, $parser->spit(), "\n", 'a');
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);
    }

    public function testSpitReturnsNullAtBeginning()
    {
        $parser = $this->createParser('abc');

        $this->assertNull($parser->spit());
    }

    public function testEatChar()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->eatChar('a'), 'b', 'b');
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage Unexpected
     */
    public function testEatCharThrowsExceptionOnUnexpectedChar()
    {
        $parser = $this->createParser('abc');

        $parser->eatChar('x');
    }

    public function testEatEol()
    {
        $parser = $this->createParser("\nx");
        $this->assertParserMethodOutcome($parser, $parser->eatEol(), "\n", 'x');

        $parser = $this->createParser("\r\nx");
        $this->assertParserMethodOutcome($parser, $parser->eatEol(), "\r\n", 'x');

        $parser = $this->createParser("\rx");
        $this->assertParserMethodOutcome($parser, $parser->eatEol(), "\r", 'x');
    }

    public function testEatIfChar()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->eatIfChar('a'), true, 'b');
        $this->assertParserMethodOutcome($parser, $parser->eatIfChar('x'), false, 'b');
    }

    public function testEatRest()
    {
        $parser = $this->createParser('abc');

        $this->assertParserMethodOutcome($parser, $parser->eatRest(), 'abc', null);
    }

    public function provideEatTypeSamples()
    {
        return array(
            array(Parser::CHAR_WS, '  foo', '  '),
            array(Parser::CHAR_NUM, '1234x', '1234'),
            array(Parser::CHAR_IDT, 'foo_bar+', 'foo_bar'),
            array(Parser::CHAR_CTRL, '++foo', '++'),
            array(Parser::CHAR_OTHER, chr(0) . chr(1) . 'a', chr(0) . chr(1)),
            array(Parser::CHAR_NONE, 'foo', ''),
        );
    }

    /**
     * @dataProvider provideEatTypeSamples
     * @param int $type
     * @param string $data
     * @param string $expectedOutput
     */
    public function testEatType($type, $data, $expectedOutput)
    {
        $parser = $this->createParser($data);

        $this->assertParserMethodOutcome($parser, $parser->eatType($type), $expectedOutput);
    }

    public function testEatTypes()
    {
        $parser = $this->createParser('foo123bar+');

        $typeMap = array(
            Parser::CHAR_IDT => 0,
            Parser::CHAR_NUM => 1,
        );

        $this->assertParserMethodOutcome($parser, $parser->eatTypes($typeMap), 'foo123bar', '+');
    }

    public function testEatUntil()
    {
        $parser = $this->createParser('abc,def,ghi');

        $this->assertParserMethodOutcome($parser, $parser->eatUntil(',', true, true), 'abc', 'd');
        $this->assertParserMethodOutcome($parser, $parser->eatUntil(array(',' => 0), false, true), 'def', ',');
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage Unexpected end
     */
    public function testEatUntilWithDisallowedEnd()
    {
        $parser = $this->createParser('abc');

        $parser->eatUntil(array(',' => 0), true, false);
    }

    public function testEatUntilEol()
    {
        $parser = $this->createParser("abc\r\nd\r\n");

        $this->assertParserMethodOutcome($parser, $parser->eatUntilEol(true), 'abc', 'd');
        $this->assertParserMethodOutcome($parser, $parser->eatUntilEol(false), 'd', "\r");
    }

    public function testEatWs()
    {
        $parser = $this->createParser("    \na");

        $this->assertParserMethodOutcome($parser, $parser->eatWs(true), 'a', 'a');
    }

    public function testEatWsNoNewlines()
    {
        $parser = $this->createParser("    \na");

        $this->assertParserMethodOutcome($parser, $parser->eatWs(false), "\n", "\n");
    }

    public function testExpectChar()
    {
        $parser = $this->createParser('a');

        $parser->expectChar('a');
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage Unexpected
     */
    public function testExpectCharThrowsExceptionOnUnexpectedChar()
    {
        $parser = $this->createParser('a');

        $parser->expectChar('x');
    }

    public function testExpectCharType()
    {
        $parser = $this->createParser('a');

         $parser->expectCharType(Parser::CHAR_IDT);
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage Unexpected
     */
    public function testExpectCharTypeThrowsExceptionOnUnexpectedChar()
    {
        $parser = $this->createParser('a');

        $parser->expectCharType(Parser::CHAR_NUM);
    }

    public function testExpectEnd()
    {
        $parser = $this->createParser('');

        $parser->expectEnd();
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage Expected end
     */
    public function testExpectEndThrowsExceptionOnUnexpectedEnd()
    {
        $parser = $this->createParser('not an end');

        $parser->expectEnd();
    }

    public function testExpectNotEnd()
    {
        $parser = $this->createParser('not an end');

        $parser->expectNotEnd();
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage Unexpected end
     */
    public function testExpectNotEndThrowsExceptionOnUnexpectedEnd()
    {
        $parser = $this->createParser('');

        $parser->expectNotEnd();
    }

    public function testSeekForward()
    {
        $parser = $this->createParser("abc\ndef");

        $parser->seek(1);
        $this->assertParserState($parser, 'b', 'a', 1, 1, false, false);

        $parser->seek(3);
        $this->assertParserState($parser, 'd', "\n", 2, 4, false, false);

        $parser->seek(3);
        $this->assertParserState($parser, null, 'f', 2, 7, false, true);
    }

    public function testSeekBackward()
    {
        $parser = $this->createParser("abc\ndef");

        $parser->eatRest();
        $this->assertParserState($parser, null, 'f', 2, 7, false, true);

        $parser->seek(-1);
        $this->assertParserState($parser, 'f', 'e', 2, 6, false, false);

        $parser->seek(-4);
        $this->assertParserState($parser, 'c', 'b', 1, 2, false, false);
    }

    public function testSeekMaintainsCorrectLineNumber()
    {
        $parser = $this->createParser("a\nb\nc\nd");

        $parser->seek(4);
        $this->assertSame(3, $parser->line);

        $parser->seek(-4);
        $this->assertSame(1, $parser->line);
    }

    public function testSeekZeroOffsetDoesNothing()
    {
        $parser = $this->createParser('baz');

        $parser->eat();

        $this->assertParserState($parser, 'a', 'b', 1, 1, false, false);
        $parser->seek(0);
        $this->assertParserState($parser, 'a', 'b', 1, 1, false, false);
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage out of boundaries
     */
    public function testSeekThrowsExceptionIfOutOfBoundsPositive()
    {
        $parser = $this->createParser('abc');

        $parser->seek(100);
    }    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage out of boundaries
     */
    public function testSeekThrowsExceptionIfOutOfBoundsNegative()
    {
        $parser = $this->createParser('abc');

        $parser->seek(-100);
    }

    public function testSeekAbsolute()
    {
        $parser = $this->createParser('baz');

        $parser->eat();

        $parser->seek(2, true);
        $this->assertParserState($parser, 'z', 'a', 1, 2, false, false);

        $parser->seek(0, true);
        $this->assertParserState($parser, 'b', null, 1, 0, false, false);
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage out of boundaries
     */
    public function testSeekAbsoluteThrowsExceptionIfOutOfBoundsPositive()
    {
        $parser = $this->createParser('abc');

        $parser->seek(100, true);
    }
    
    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage out of boundaries
     */
    public function testSeekAbsoluteThrowsExceptionIfOutOfBoundsNegative()
    {
        $parser = $this->createParser('abc');

        $parser->seek(-100, true);
    }

    public function testJumpForward()
    {
        $parser = $this->createParser("abc\ndef", false);

        $parser->seek(1);
        $this->assertParserState($parser, 'b', 'a', null, 1, false, false);

        $parser->seek(3);
        $this->assertParserState($parser, 'd', "\n", null, 4, false, false);

        $parser->seek(3);
        $this->assertParserState($parser, null, 'f', null, 7, false, true);
    }

    public function testJumpBackward()
    {
        $parser = $this->createParser("abc\ndef", false);

        $parser->eatRest();
        $this->assertParserState($parser, null, 'f', null, 7, false, true);

        $parser->seek(-1);
        $this->assertParserState($parser, 'f', 'e', null, 6, false, false);

        $parser->seek(-3);
        $this->assertParserState($parser, "\n", 'c', null, 3, true, false);
    }

    public function testJumpIgnoresLineNumber()
    {
        $parser = $this->createParser("a\nb\nc\nd", false);

        $parser->seek(4);
        $this->assertNull($parser->line);

        $parser->seek(-4);
        $this->assertNull($parser->line);
    }

    public function testJumpZeroOffsetDoesNothing()
    {
        $parser = $this->createParser('baz', false);

        $parser->eat();

        $this->assertParserState($parser, 'a', 'b', null, 1, false, false);
        $parser->seek(0);
        $this->assertParserState($parser, 'a', 'b', null, 1, false, false);
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage out of boundaries
     */
    public function testJumpThrowsExceptionIfOutOfBoundsPositive()
    {
        $parser = $this->createParser('abc', false);

        $parser->seek(100);
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage out of boundaries
     */
    public function testJumpThrowsExceptionIfOutOfBoundsNegative()
    {
        $parser = $this->createParser('abc', false);

        $parser->seek(-100);
    }

    public function testJumpAbsolute()
    {
        $parser = $this->createParser('baz', false);

        $parser->eat();

        $parser->seek(2, true);
        $this->assertParserState($parser, 'z', 'a', null, 2, false, false);

        $parser->seek(0, true);
        $this->assertParserState($parser, 'b', null, null, 0, false, false);
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage out of boundaries
     */
    public function testJumpAbsoluteThrowsExceptionIfOutOfBoundsPositive()
    {
        $parser = $this->createParser('abc', false);

        $parser->seek(100, true);
    }

    /**
     * @expectedException        Kuria\Parser\ParserException
     * @expectedExceptionMessage out of boundaries
     */
    public function testJumpAbsoluteThrowsExceptionIfOutOfBoundsNegative()
    {
        $parser = $this->createParser('abc', false);

        $parser->seek(-100, true);
    }

    public function testRewind()
    {
        $parser = $this->createParser("a\nabc");

        $parser->eat();
        $parser->rewind();

        $this->assertParserState($parser, 'a', null, 1, 0, false, false);
    }

    public function testReset()
    {
        $parser = $this->createParser("a\nabc");

        $parser->eat();
        $parser->pushState();
        $parser->reset();

        $this->assertParserState($parser, 'a', null, 1, 0, false, false);
        $this->assertSame(0, $parser->getNumStates());
    }

    public function testPeek()
    {
        $parser = $this->createParser('abc');

        $this->assertSame(null, $parser->peek(-1));
        $this->assertSame('a', $parser->peek(0));
        $this->assertSame('b', $parser->peek(1));
        $this->assertSame('c', $parser->peek(2));
        $this->assertSame(null, $parser->peek(3));
    }

    public function testChunk()
    {
        $parser = $this->createParser('aaaaabbbbbcccccx');

        // chunking should just load data and do not affect parser state
        $this->assertSame('aaaaa', $parser->chunk(0, 5));
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);

        $this->assertSame('aaaab', $parser->chunk(1, 5));
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);

        $this->assertSame('bccccc', $parser->chunk(9, 6));
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);

        $this->assertSame('bccccc', $parser->chunk(9, 6));
        $this->assertParserState($parser, 'a', null, 1, 0, false, false);

        // chunks beyond available range should contain all available data
        $this->assertSame('x', $parser->chunk(15, 10));

        // chunking past available range should yield an empty chunk
        $this->assertSame('', $parser->chunk(100, 5));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testChunkThrowsExceptionOnNegativePosition()
    {
        $this->createParser('Hello world')->chunk(-1, 5);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testChunkThrowsExceptionOnZeroLength()
    {
        $this->createParser('Hello world')->chunk(0, 0);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testChunkThrowsExceptionOnNegativeLength()
    {
        $this->createParser('Hello world')->chunk(0, -1);
    }

    public function testStates()
    {
        $parser = $this->createParser("Lorem\nIpsum\nDolor\n");

        $this->assertSame(0, $parser->getNumStates());

        $parser->seek(6);
        $this->assertParserState($parser, 'I', "\n", 2, 6, false, false, array());

        $parser->pushState();
        $this->assertSame(1, $parser->getNumStates());

        $parser->eatRest();
        $parser->vars['foo'] = 'bar';
        $this->assertParserState($parser, null, "\n", 4, 18, false, true, array('foo' => 'bar'));

        $parser->pushState();
        $this->assertSame(2, $parser->getNumStates());

        $parser->rewind();
        unset($parser->vars['foo']);
        $charTypeAtEnd = $parser->charType;
        $this->assertParserState($parser, 'L', null, 1, 0, false, false, array());

        $parser->revertState();
        $this->assertParserState($parser, null, "\n", 4, 18, false, true, array('foo' => 'bar'));
        $this->assertSame(1, $parser->getNumStates());
        $this->assertNotSame($charTypeAtEnd, $parser->charType);

        $parser->revertState();
        $this->assertParserState($parser, 'I', "\n", 2, 6, false, false, array());
        $this->assertSame(0, $parser->getNumStates());

        $parser->pushState();
        $parser->eat();
        $parser->popState();
        $this->assertParserState($parser, 'p', 'I', 2, 7, false, false, array());
    }

    /**
     * @expectedException        RuntimeException
     * @expectedExceptionMessage No states active
     */
    public function testRevertStateThrowsExceptionIfNoStates()
    {
        $parser = $this->createParser();

        $parser->revertState();
    }

    /**
     * @expectedException        RuntimeException
     * @expectedExceptionMessage No states active
     */
    public function testPopStateThrowsExceptionIfNoStates()
    {
        $parser = $this->createParser();

        $parser->popState();
    }

    public function testClearStates()
    {
        $parser = $this->createParser();

        $parser->pushState();
        $parser->clearStates();

        $this->assertSame(0, $parser->getNumStates());
    }
    
    public function testLineTrackingDisabled()
    {
        $parser = $this->createParser("foo\nbar", false);

        $this->assertNull($parser->line);
        $parser->eatRest();
        $this->assertNull($parser->line);
    }

    /**
     * Assert that result of some operation on the parser matches the expected outcome
     *
     * @param Parser      $parser
     * @param mixed       $actualResult
     * @param mixed       $expectedResult
     * @param string|null $expectedCurrentChar
     */
    protected function assertParserMethodOutcome(Parser $parser, $actualResult, $expectedResult, $expectedCurrentChar = null)
    {
        $this->assertSame($expectedResult, $actualResult, 'actual and expected result must match');

        if (func_num_args() >= 4) {
            $this->assertSame($expectedCurrentChar, $parser->char, sprintf('expected current character to be "%s"', $expectedCurrentChar));
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
    protected function assertParserState($parser, $expectedChar, $expectedLastChar, $expectedLine, $expectedOffset, $expectedAtNewline, $expectedEnd, array $expectedVars = null)
    {
        $this->assertSame($expectedChar, $parser->char, sprintf('expected current character to be "%s"', $expectedChar));
        $this->assertSame($expectedLastChar, $parser->lastChar, sprintf('expected last character to be "%s"', $expectedLastChar));
        $this->assertSame($expectedLine, $parser->line, sprintf('expected current line to be %d', $expectedLine));
        $this->assertSame($expectedOffset, $parser->i, sprintf('expected current offset to be %d', $expectedOffset));
        $this->assertSame($expectedAtNewline, $parser->atNewline(), sprintf('expected atNewline() to yield %s', $expectedAtNewline ? 'true' : 'false'));
        $this->assertSame($expectedEnd, $parser->end, sprintf('expected end to be %s', $expectedEnd ? 'true' : 'false'));

        if ($expectedVars !== null) {
            $this->assertSame($expectedVars, $parser->vars, 'expected vars to match');
        }
    }
}
