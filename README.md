Chess 0.8.18
============
Show chess diagrams.

<p align="center"><img src="chess-screenshot.png?raw=true" width="795" height="836" alt="Screenshot"></p>

## How to show a chess diagram

Create a `[chess]` shortcut. 

The following arguments are available, all but the first argument are optional:
 
`Position` = board position in Forsyth–Edwards Notation (FEN), wrap more fields into quotes  
`Style` = diagram style, e.g. `left`, `center`, `right`  
`Width` = diagram width, pixel or percent  

[Forsyth–Edwards Notation](http://www.saremba.de/chessgml/standards/pgn/pgn-complete.htm#c16.1) is the standard way of describing board positions in chess. In addition to piece placement it allows the indication of active color, castling availability, en passant target square, halfmove clock, fullmove number. You can however here omit any additional field from the right.

## Examples

Showing a chess diagram:

    [chess r5k1/3q1pp1/pp2rnnp/4p3/P2P4/1QP1BN1P/5PP1/3RR1K1]
    [chess "r5k1/3q1pp1/pp2rnnp/4p3/P2P4/1QP1BN1P/5PP1/3RR1K1 b"]
    [chess "r5k1/3q1pp1/pp2rnnp/4p3/P2P4/1QP1BN1P/5PP1/3RR1K1 b - - 0 21"]

Showing a chess diagram, different styles:

    [chess "r5k1/3q1pp1/pp2rnnp/4p3/P2P4/1QP1BN1P/5PP1/3RR1K1 b" left]
    [chess "r5k1/3q1pp1/pp2rnnp/4p3/P2P4/1QP1BN1P/5PP1/3RR1K1 b" center]
    [chess "r5k1/3q1pp1/pp2rnnp/4p3/P2P4/1QP1BN1P/5PP1/3RR1K1 b" right]

Showing a chess diagram, different sizes:

    [chess "r5k1/3q1pp1/pp2rnnp/4p3/P2P4/1QP1BN1P/5PP1/3RR1K1 b" right 50%]
    [chess "r5k1/3q1pp1/pp2rnnp/4p3/P2P4/1QP1BN1P/5PP1/3RR1K1 b" right 200]

Showing a chess diagram, different sizes with the default style:

    [chess "r5k1/3q1pp1/pp2rnnp/4p3/P2P4/1QP1BN1P/5PP1/3RR1K1 b" - 50%]
    [chess "r5k1/3q1pp1/pp2rnnp/4p3/P2P4/1QP1BN1P/5PP1/3RR1K1 b" - 200]

## Settings

The following settings can be configured in file `system/extensions/yellow-system.ini`:

`ChessShowDots` (default `1`) = show in the diagram the active color  
`ChessPieceList` (default `0`) = use as alternative text a list of the pieces, instead of the FEN code  
`ChessWidth` (default `300`) = default diagram width  

## Installation

[Download extension](https://github.com/GiovanniSalmeri/yellow-chess/archive/master.zip) and copy zip file into your `system/extensions` folder. Right click if you use Safari.

## Developer

Giovanni Salmeri. [Get help](https://github.com/GiovanniSalmeri/yellow-chess/issues).
