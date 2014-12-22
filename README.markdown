Parser
======

Character-by-character string parsing library.


## Features

- base parser classes
    - use directly or subclass
- input abstraction
    - read a string directly (`MemoryInput`)
    - read a stream chunked to the desired size (`StreamInput`)
    - implement your own
- newline aware
   - supports CR, LF and CRLF line endings
   - line number tracking
   - EOL detection
- many methods to navigate and operate the parser
    - forward / backward peeking and seeking
    - forward / backward character consumption
    - state stack
- current state tracking
    - position
    - current character
    - type of the current character
    - last character
    - end state
- character types
    - whitespace
    - number
    - identifier
    - control
    - other
- expectations
    - about current character
    - about end state
- exceptions
    - nice error messages with current line and/or offset


## Requirements

- PHP 5.3 or newer


## Performance

### Speed of the parser

The parser has been optimized for performance where possible, but it can never compete with
very simple loops or native C code (regular expressions). This is due to the fact that function
calls in PHP are quite expensive. Even a single function call per character results in about
a 90% drop in raw performance. Please refer to the table below.

<table>
    <thead>
        <tr>
            <th>Process</th>
            <th>Time needed</th>
            <th>Performance</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><code>InputParser</code> class</td>
            <td>1</td>
            <td>base</td>
        </tr>
        <tr>
            <td>simple <code>for</code> loop + 1 function call</td>
            <td>0.59</td>
            <td>+69%</td>
        </tr>
        <tr>
            <td>simple <code>for</code> loop + no function calls</td>
            <td>0.06</td>
            <td>+1566%</td>
        </tr>
        <tr>
            <td>regular expression</td>
            <td>0.02</td>
            <td>+4900%</td>
        </tr>
    </tbody>
</table>


### Notes

- parsing speed should not matter very much, since the results **should** be cached
- the `InputParser` class does not introduce much overhead over a simple `for` loop containing
  1 function call, while providing all of the additional functionality



## Usage example

### A simple INI parser


    <?php

    use Kuria\Parser\InputParser;

    /**
     * INI parser
     *
     * Serves as an InputParser usage example and is not supposed to be complete.
     */
    class IniParser
    {
        /**
         * Parse an INI string
         *
         * @param string $string
         * @return array
         */
        public function parse($string)
        {
            // create parser
            $parser = InputParser::fromString($string);

            // prepare variables
            $data = array();
            $currentSection = null;

            // parse
            while (!$parser->end) {
                // skip whitespace
                $parser->eatWs();
                if ($parser->end) {
                    break;
                }

                // parse the current thing
                if ('[' === $parser->char) {
                    // a section
                    $currentSection = $this->parseSection($parser);
                } elseif (';' === $parser->char) {
                    // a comment
                    $this->parseComment($parser);
                } else {
                    // a key=value pair
                    list($key, $value) = $this->parseKeyValue($parser);

                    // add to output
                    if (null === $currentSection) {
                        $data[$key] = $value;
                    } else {
                        $data[$currentSection][$key] = $value;
                    }
                }
            }

            return $data;
        }

        /**
         * Parse a section
         *
         * @param InputParser $parser
         * @return string
         */
        protected function parseSection(InputParser $parser)
        {
            // we should be at the [ character now, eat it
            $parser->eatChar('[');

            // eat everything until ]
            $sectionName = $parser->eatUntil(']');

            return $sectionName;
        }

        /**
         * Parse a comment
         *
         * @param InputParser $parser
         */
        protected function parseComment(InputParser $parser)
        {
            // we should be at the ; character now, eat it
            $parser->eatChar(';');

            // eat everything until the end of line
            $parser->eatUntilEol();
        }

        /**
         * Parse a key=value pair
         *
         * @param InputParser $parser
         * @return array key, value
         */
        protected function parseKeyValue(InputParser $parser)
        {
            // we should be at the first character of the key
            // eat characters until = is found
            $key = $parser->eatUntil('=');

            // eat everything until the end of line
            // that is our value
            $value = trim($parser->eatUntilEol());

            return array($key, $value);
        }
    }

### Usage

    <?php

    $iniParser = new IniParser();

    $iniString = '
    ; An example comment
    name=Foo
    type=Bar

    [options]
    size=150x100
    onload=
    ';

    $data = $iniParser->parse($iniString);

    print_r($data);

### Output

    Array
    (
        [name] => Foo
        [type] => Bar
        [options] => Array
            (
                [size] => 150x100
                [onload] =>
            )

    )
