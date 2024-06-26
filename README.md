# Chess 0.9.1

Chess diagrams and games.

<p align="center"><img src="SCREENSHOT.png" alt="Screenshot"></p>

## How to install an extension

[Download ZIP file](https://github.com/GiovanniSalmeri/yellow-chess/archive/refs/heads/main.zip) and copy it into your `system/extensions` folder. [Learn more about extensions](https://github.com/annaesvensson/yellow-update).

## How to show a chess diagram or game

Create a `[chess]` shortcut. 

The following arguments are available, all but the first argument are optional:
 
`Source` = board position in Forsyth–Edwards Notation (FEN), or file name of game in Portable Game Notation (PGN); wrap more fields into quotes  
`Style` = diagram style, e.g. `left`, `center`, `right`  
`Width` = diagram width, pixel or percent  

The `media/chess` folder is the place to store your board positions and games. You can also create additional folders and organise files as you like.

[Forsyth–Edwards Notation](http://www.saremba.de/chessgml/standards/pgn/pgn-complete.htm#c16.1) is the standard way of describing board positions in chess. In addition to piece placement it allows the indication of active color, castling availability, en passant target square, halfmove clock, fullmove number. In the shortcut you can omit any additional field from the right.

[Portable Game Notation](http://www.saremba.de/chessgml/standards/pgn/pgn-complete.htm) is the standard way of describing chess games. Should the file contain more games, the first is picked. You can add after the file name two additional fields: the first specifies a move or interval of moves, e.g. `begin-5w`, `5b-12w`, `15w-end` (`begin` means the initial position, `end` the final result of the game); the second specifies the output: `diagram` of the position after the last move specified, list of `moves`, or `all`. With `begin`, `moves` outputs the heading of the game. Default values are `begin-end all`.

## How to add comments to a game

You can divide a game into several parts by specifying a range of moves in the `Source` argument. After each section you can add comments. Write the moves in the Standard Algebraic Notation and they will automatically translated (figurines or localised abbreviations).

You can also add comments in the PGN file, enclosed in `{}`.

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

Showing a chess game, all or partially:

    [chess caruana_ponomariov_2014.pgn]
    [chess "caruana_ponomariov_2014.pgn begin-12w"]
    [chess "caruana_ponomariov_2014.pgn 21w"]
    [chess "caruana_ponomariov_2014.pgn 10w-end"]

Showing a chess game with a diagram, moves or both:

    [chess "caruana_ponomariov_2014.pgn 12w diagram"]
    [chess "caruana_ponomariov_2014.pgn begin-12w moves"]
    [chess "caruana_ponomariov_2014.pgn begin-12w all"]

Showing a chess diagram from a game, different sizes with the default style:

    [chess "caruana_ponomariov_2014.pgn 12w diagram" - 50%]
    [chess "caruana_ponomariov_2014.pgn 12w diagram" - 200]

Adding comments:

    [chess "byrne_fischer_1956.pgn begin-4b"]
    
    Fischer castles, bringing his king to safety. The Black move 4...d5 
    would have reached the Grünfeld Defence immediately. After Fischer's 
    4...O-O, Byrne could have played 5.e4, whereupon 5...d6 6.Be2 e5 
    reaches the main line of the King's Indian Defense.
    
    [chess "byrne_fischer_1956.pgn 5w-6w"]
    
    A form of the so-called Russian System (the usual move order is 1.d4 
    Nf6 2.c4 g6 3.Nc3 d5 4.Nf3 Bg7 5.Qb3), putting pressure on Fischer's 
    central d5-pawn.

## Settings

The following settings can be configured in file `system/extensions/yellow-system.ini`:

`ChessDirectory` = directory for PGN games  
`ChessMoveStyle` = moves style, `figurines`, `standard`, or `letters`  
`ChessShowCoordinates` = show in the diagram the coordinates, 0 or 1  
`ChessShowDots` = show in the diagram the active color, 0 or 1  
`ChessWidth` = default diagram width  
`ChessPieceList` = use as alternative text a list of the pieces, instead of the FEN code, 0 or 1  

## Developer

Giovanni Salmeri. [Get help](https://datenstrom.se/yellow/help/).
