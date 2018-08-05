Parser
######

Character-by-character string parsing library.

.. image:: https://travis-ci.org/kuria/parser.svg?branch=master
   :target: https://travis-ci.org/kuria/parser

.. contents::
   :depth: 2


Features
********

- line number tracking (can be disabled for performance)
- supports CR, LF and CRLF line endings
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


Usage
*****

Creating a parser
=================

Create a new parser instance with string input.

The parser begins at the first character.

.. code:: php

   <?php

   use Kuria\Parser\Parser;

   $input = 'foo bar baz';

   $parser = new Parser($input);


Parser properties
=================

The parser has several public properties that can be used to inspect its
current state:

- ``$parser->i`` - current position
- ``$parser->char`` - current character (or ``NULL`` at the end of input)
- ``$parser->lastChar`` - last character (or ``NULL`` at the start of input)
- ``$parser->line`` - current line (or ``NULL`` if line tracking is disabled)
- ``$parser->end`` - end of input indicator (``TRUE`` at the end, ``FALSE`` otherwise)
- ``$parser->vars`` - user-defined variables attached to the current state

.. WARNING::

   All of the public properties (with the exception of ``$parser->vars``)
   are read-only and must not be modified directly by the calling code.

   Use the built-in parser methods to mutate the parser state.
   See `Parser method overview`_.


Parser method overview
======================

Refer to doc comments of the respective methods for more information.

Also see `Character types`_.


Static methods
--------------

- ``getCharType($char): int`` - determine character type
- ``getCharTypeName($charType): string`` - get human-readable character type name


Instance methods
----------------

- ``getInput(): string`` - get the input string
- ``setInput($input): void`` - replace the input string (this also resets the parser)
- ``getLength(): int`` - get length of the input string
- ``isTrackingLineNumbers(): bool`` - see if line number tracking is enabled
- ``type(): int`` - get type of the current character
- ``is(...$types): bool`` - check whether the current character is of one of the specified types
- ``atNewline(): bool`` - see if the parser is at the start of a newline sequence
- ``eat(): ?string`` - go to the next character and return the current one (returns ``NULL`` at the end)
- ``spit(): ?string`` - go to the previous character and return the current one (returns ``NULL`` at the beginning)
- ``shift(): ?string`` - go to the next character and return it (returns ``NULL`` at the end)
- ``unshift(): ?string`` - go to the previous character and return it (returns ``NULL`` at the beginning)
- ``peek($offset, $absolute = false): ?string`` - get character at the given offset or absolute position (does not affect state)
- ``seek($offset, $absolute = false): void`` - alter current position
- ``reset(): void`` - reset states, vars and rewind to the beginning
- ``rewind(): void`` - rewind to the beginning
- ``eatChar($char): ?string`` - consume specific character and return the next character
- ``tryEatChar(): bool`` - attempt to consume specific character and return success state
- ``eatType($type): string`` - consume all characters of the specified type
- ``eatTypes($typeMap): string`` - consume all characters of the specified types
- ``eatWs(): string`` - consume whitespace, if any
- ``eatUntil($delimiterMap, $skipDelimiter = true, $allowEnd = false): string`` - consume all characters until the specified delimiters
- ``eatUntilEol($skip = true): string`` - consume all character until end of line or input
- ``eatEol(): string`` - consume end of line sequence
- ``eatRest(): string`` - consume reamaining characters
- ``getChunk($start, $end): string`` - get chunk of the input (does not affect state)
- ``detectEol(): ?string`` - find and return the next end of line sequence (does not affect state)
- ``countStates(): int`` - get number of stored states
- ``pushState(): void`` - store the current state
- ``revertState(): void`` - revert to the last stored state and pop it
- ``popState(): void`` - pop the last stored state without reverting to it
- ``clearStates(): void`` - throw away all stored states
- ``expectEnd(): void`` - ensure that the parser is at the end
- ``expectNotEnd(): void`` - ensure that the parser is not at the end
- ``expectChar($expectedChar): void`` - ensure that the current character matches the expectation
- ``expectCharType($expectedType): void`` - ensure that the current character is of the given type


Example INI parser implementation
=================================

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
           $parser = new Parser($string);

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
       private function parseSection(Parser $parser): string
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
       private function skipComment(Parser $parser): void
       {
           // we should be at the ; character now, eat it
           $parser->eatChar(';');

           // eat everything until the end of line
           $parser->eatUntilEol();
       }

       /**
        * Parse a key=value pair
        */
       private function parseKeyValue(Parser $parser): array
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


Character types
***************

The table below lists the default character types.

These types are available as constants on the ``Parser class``:

- ``Parser::C_NONE`` - no character (NULL)
- ``Parser::C_WS`` - whitespace (tab, linefeed, vertical tab, form feed, carriage return and space)
- ``Parser::C_NUM`` - numeric character (``0-9``)
- ``Parser::C_STR`` - string character (``a-z``, ``A-Z``, ``_`` and any 8-bit char)
- ``Parser::C_CTRL`` - control character (ASCII 127 and ASCII < 32 except whitespace)
- ``Parser::C_SPECIAL`` - ``!"#$%&'()*+,-./:;<=>?@[\\]^\`{|}~``



==== ========= =========
#    Character Type
==== ========= =========
NULL *none*    C_NONE
0    ``0x00``  C_CTRL
1    ``0x01``  C_CTRL
2    ``0x02``  C_CTRL
3    ``0x03``  C_CTRL
4    ``0x04``  C_CTRL
5    ``0x05``  C_CTRL
6    ``0x06``  C_CTRL
7    ``0x07``  C_CTRL
8    ``0x08``  C_CTRL
9    ``\t``    C_WS
10   ``\n``    C_WS
11   ``\v``    C_WS
12   ``\f``    C_WS
13   ``\r``    C_WS
14   ``0x0e``  C_CTRL
15   ``0x0f``  C_CTRL
16   ``0x10``  C_CTRL
17   ``0x11``  C_CTRL
18   ``0x12``  C_CTRL
19   ``0x13``  C_CTRL
20   ``0x14``  C_CTRL
21   ``0x15``  C_CTRL
22   ``0x16``  C_CTRL
23   ``0x17``  C_CTRL
24   ``0x18``  C_CTRL
25   ``0x19``  C_CTRL
26   ``0x1a``  C_CTRL
27   ``0x1b``  C_CTRL
28   ``0x1c``  C_CTRL
29   ``0x1d``  C_CTRL
30   ``0x1e``  C_CTRL
31   ``0x1f``  C_CTRL
32   ``0x20``  C_WS
33   ``!``     C_SPECIAL
34   ``"``     C_SPECIAL
35   ``#``     C_SPECIAL
36   ``$``     C_SPECIAL
37   ``%``     C_SPECIAL
38   ``&``     C_SPECIAL
39   ``'``     C_SPECIAL
40   ``(``     C_SPECIAL
41   ``)``     C_SPECIAL
42   ``*``     C_SPECIAL
43   ``+``     C_SPECIAL
44   ``,``     C_SPECIAL
45   ``-``     C_SPECIAL
46   ``.``     C_SPECIAL
47   ``/``     C_SPECIAL
48   ``0``     C_NUM
49   ``1``     C_NUM
50   ``2``     C_NUM
51   ``3``     C_NUM
52   ``4``     C_NUM
53   ``5``     C_NUM
54   ``6``     C_NUM
55   ``7``     C_NUM
56   ``8``     C_NUM
57   ``9``     C_NUM
58   ``:``     C_SPECIAL
59   ``;``     C_SPECIAL
60   ``<``     C_SPECIAL
61   ``=``     C_SPECIAL
62   ``>``     C_SPECIAL
63   ``?``     C_SPECIAL
64   ``@``     C_SPECIAL
65   ``A``     C_STR
66   ``B``     C_STR
67   ``C``     C_STR
68   ``D``     C_STR
69   ``E``     C_STR
70   ``F``     C_STR
71   ``G``     C_STR
72   ``H``     C_STR
73   ``I``     C_STR
74   ``J``     C_STR
75   ``K``     C_STR
76   ``L``     C_STR
77   ``M``     C_STR
78   ``N``     C_STR
79   ``O``     C_STR
80   ``P``     C_STR
81   ``Q``     C_STR
82   ``R``     C_STR
83   ``S``     C_STR
84   ``T``     C_STR
85   ``U``     C_STR
86   ``V``     C_STR
87   ``W``     C_STR
88   ``X``     C_STR
89   ``Y``     C_STR
90   ``Z``     C_STR
91   ``[``     C_SPECIAL
92   ``\``     C_SPECIAL
93   ``]``     C_SPECIAL
94   ``^``     C_SPECIAL
95   ``_``     C_STR
96   \`        C_SPECIAL
97   ``a``     C_STR
98   ``b``     C_STR
99   ``c``     C_STR
100  ``d``     C_STR
101  ``e``     C_STR
102  ``f``     C_STR
103  ``g``     C_STR
104  ``h``     C_STR
105  ``i``     C_STR
106  ``j``     C_STR
107  ``k``     C_STR
108  ``l``     C_STR
109  ``m``     C_STR
110  ``n``     C_STR
111  ``o``     C_STR
112  ``p``     C_STR
113  ``q``     C_STR
114  ``r``     C_STR
115  ``s``     C_STR
116  ``t``     C_STR
117  ``u``     C_STR
118  ``v``     C_STR
119  ``w``     C_STR
120  ``x``     C_STR
121  ``y``     C_STR
122  ``z``     C_STR
123  ``{``     C_SPECIAL
124  ``|``     C_SPECIAL
125  ``}``     C_SPECIAL
126  ``~``     C_SPECIAL
127  ``0x7f``  C_CTRL
128  ``0x80``  C_STR
129  ``0x81``  C_STR
130  ``0x82``  C_STR
131  ``0x83``  C_STR
132  ``0x84``  C_STR
133  ``0x85``  C_STR
134  ``0x86``  C_STR
135  ``0x87``  C_STR
136  ``0x88``  C_STR
137  ``0x89``  C_STR
138  ``0x8a``  C_STR
139  ``0x8b``  C_STR
140  ``0x8c``  C_STR
141  ``0x8d``  C_STR
142  ``0x8e``  C_STR
143  ``0x8f``  C_STR
144  ``0x90``  C_STR
145  ``0x91``  C_STR
146  ``0x92``  C_STR
147  ``0x93``  C_STR
148  ``0x94``  C_STR
149  ``0x95``  C_STR
150  ``0x96``  C_STR
151  ``0x97``  C_STR
152  ``0x98``  C_STR
153  ``0x99``  C_STR
154  ``0x9a``  C_STR
155  ``0x9b``  C_STR
156  ``0x9c``  C_STR
157  ``0x9d``  C_STR
158  ``0x9e``  C_STR
159  ``0x9f``  C_STR
160  ``0xa0``  C_STR
161  ``0xa1``  C_STR
162  ``0xa2``  C_STR
163  ``0xa3``  C_STR
164  ``0xa4``  C_STR
165  ``0xa5``  C_STR
166  ``0xa6``  C_STR
167  ``0xa7``  C_STR
168  ``0xa8``  C_STR
169  ``0xa9``  C_STR
170  ``0xaa``  C_STR
171  ``0xab``  C_STR
172  ``0xac``  C_STR
173  ``0xad``  C_STR
174  ``0xae``  C_STR
175  ``0xaf``  C_STR
176  ``0xb0``  C_STR
177  ``0xb1``  C_STR
178  ``0xb2``  C_STR
179  ``0xb3``  C_STR
180  ``0xb4``  C_STR
181  ``0xb5``  C_STR
182  ``0xb6``  C_STR
183  ``0xb7``  C_STR
184  ``0xb8``  C_STR
185  ``0xb9``  C_STR
186  ``0xba``  C_STR
187  ``0xbb``  C_STR
188  ``0xbc``  C_STR
189  ``0xbd``  C_STR
190  ``0xbe``  C_STR
191  ``0xbf``  C_STR
192  ``0xc0``  C_STR
193  ``0xc1``  C_STR
194  ``0xc2``  C_STR
195  ``0xc3``  C_STR
196  ``0xc4``  C_STR
197  ``0xc5``  C_STR
198  ``0xc6``  C_STR
199  ``0xc7``  C_STR
200  ``0xc8``  C_STR
201  ``0xc9``  C_STR
202  ``0xca``  C_STR
203  ``0xcb``  C_STR
204  ``0xcc``  C_STR
205  ``0xcd``  C_STR
206  ``0xce``  C_STR
207  ``0xcf``  C_STR
208  ``0xd0``  C_STR
209  ``0xd1``  C_STR
210  ``0xd2``  C_STR
211  ``0xd3``  C_STR
212  ``0xd4``  C_STR
213  ``0xd5``  C_STR
214  ``0xd6``  C_STR
215  ``0xd7``  C_STR
216  ``0xd8``  C_STR
217  ``0xd9``  C_STR
218  ``0xda``  C_STR
219  ``0xdb``  C_STR
220  ``0xdc``  C_STR
221  ``0xdd``  C_STR
222  ``0xde``  C_STR
223  ``0xdf``  C_STR
224  ``0xe0``  C_STR
225  ``0xe1``  C_STR
226  ``0xe2``  C_STR
227  ``0xe3``  C_STR
228  ``0xe4``  C_STR
229  ``0xe5``  C_STR
230  ``0xe6``  C_STR
231  ``0xe7``  C_STR
232  ``0xe8``  C_STR
233  ``0xe9``  C_STR
234  ``0xea``  C_STR
235  ``0xeb``  C_STR
236  ``0xec``  C_STR
237  ``0xed``  C_STR
238  ``0xee``  C_STR
239  ``0xef``  C_STR
240  ``0xf0``  C_STR
241  ``0xf1``  C_STR
242  ``0xf2``  C_STR
243  ``0xf3``  C_STR
244  ``0xf4``  C_STR
245  ``0xf5``  C_STR
246  ``0xf6``  C_STR
247  ``0xf7``  C_STR
248  ``0xf8``  C_STR
249  ``0xf9``  C_STR
250  ``0xfa``  C_STR
251  ``0xfb``  C_STR
252  ``0xfc``  C_STR
253  ``0xfd``  C_STR
254  ``0xfe``  C_STR
255  ``0xff``  C_STR
==== ========= =========


Customizing character types
===========================

Character types can be customized by extending the base ``Parser`` class.

The following example changes "``-``" and "``.``" from ``CHAR_SPECIAL`` to ``CHAR_STR``
and inherits everything else.

.. code:: php

   <?php

   class CustomParser extends Parser
   {
       const CHAR_TYPE_MAP = [
           '-' => self::C_STR,
           '.' => self::C_STR,
       ] + parent::CHAR_TYPE_MAP; // inherit everything else
   }

   // usage example
   $parser = new CustomParser('foo-bar.baz');

   var_dump($parser->eatType(CustomParser::C_STR));

Output:

::

  string(11) "foo-bar.baz"
