Parser
######

Character-by-character string parsing library.

.. contents::
   :depth: 2


Features
********

- parser class
- input abstraction (parse strings in memory or from a stream)
- newline aware
- supports CR, LF and CRLF line endings
- line number tracking (can be disabled for performance)
- exceptions with current line and offset
- many methods to navigate and operate the parser

  - forward / backward peeking and seeking
  - forward / backward character consumption
  - state stack

- current state tracking
- multiple character types
- expectations


Requirements
************

- PHP 5.3.0+ or 7.0.0+


Usage example
*************

Simple INI parser implementation
================================

.. code:: php

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


Using the parser
----------------

.. code:: php

   <?php

   $iniParser = new IniParser();

   $iniString = <<<INI
   ; An example comment
   name=Foo
   type=Bar

   [options]
   size=150x100
   onload=
   INI;

   $data = $iniParser->parse($iniString);

   print_r($data);

Output:

::

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
