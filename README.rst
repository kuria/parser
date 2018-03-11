Parser
######

Character-by-character string parsing library.

.. image:: https://travis-ci.org/kuria/parser.svg?branch=master
   :target: https://travis-ci.org/kuria/parser

.. contents::
   :depth: 2


Features
********

- input abstraction (parse strings in memory or from a stream)
- supports CR, LF and CRLF line endings
- line number tracking (can be disabled for performance)
- verbose exceptions
- many methods to navigate and operate the parser

  - forward / backward peeking and seeking
  - forward / backward character consumption
  - state stack

- character types
- expectations


Requirements
************

- PHP 7.1+


Usage example
*************

Simple INI parser implementation
================================

.. code:: php

   <?php

   use Kuria\Parser\Parser;

   /**
    * INI parser (example)
    */
   class IniParser
   {
       /**
        * Parse an INI string
        */
       public function parse(string $string): array
       {
           // create parser
           $parser = Parser::fromString($string);

           // prepare variables
           $data = [];
           $currentSection = null;

           // parse
           while (!$parser->end) {
               // skip whitespace
               $parser->eatWs();
               if ($parser->end) {
                   break;
               }

               // parse the current thing
               if ($parser->char === '[') {
                   // a section
                   $currentSection = $this->parseSection($parser);
               } elseif ($parser->char === ';') {
                   // a comment
                   $this->skipComment($parser);
               } else {
                   // a key=value pair
                   [$key, $value] = $this->parseKeyValue($parser);

                   // add to output
                   if ($currentSection === null) {
                       $data[$key] = $value;
                   } else {
                       $data[$currentSection][$key] = $value;
                   }
               }
           }

           return $data;
       }

       /**
        * Parse a section and return its name
        */
       protected function parseSection(Parser $parser): string
       {
           // we should be at the [ character now, eat it
           $parser->eatChar('[');

           // eat everything until ]
           $sectionName = $parser->eatUntil(']');

           return $sectionName;
       }

       /**
        * Skip a commented-out line
        */
       protected function skipComment(Parser $parser)
       {
           // we should be at the ; character now, eat it
           $parser->eatChar(';');

           // eat everything until the end of line
           $parser->eatUntilEol();
       }

       /**
        * Parse a key=value pair
        */
       protected function parseKeyValue(Parser $parser): array
       {
           // we should be at the first character of the key
           // eat characters until = is found
           $key = $parser->eatUntil('=');

           // eat everything until the end of line
           // that is our value
           $value = trim($parser->eatUntilEol());

           return [$key, $value];
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
