<?php
// Chess extension, https://github.com/GiovanniSalmeri/yellow-chess

class YellowChess {
    const VERSION = "0.8.18";
    public $yellow;         // access to API

    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("chessDirectory", "media/chess/");
        $this->yellow->system->setDefault("chessMoveStyle", "figurines"); // standard, letters
        $this->yellow->system->setDefault("chessShowCoordinates", "0");
        $this->yellow->system->setDefault("chessShowDots", "1");
        $this->yellow->system->setDefault("chessWidth", "300");
        $this->yellow->system->setDefault("chessPieceList", "0");
    }

    // Handle page content of shortcut
    public function onParseContentShortcut($page, $name, $text, $type) {
        $output = null;
        $errors = [];
        if ($name=="chess" && ($type=="block" || $type=="inline")) {
            list($chess, $style, $width) = $this->yellow->toolbox->getTextArguments($text);
            if (preg_match('/^\S+\.pgn(\s|$)/', $chess)) { // PGN
                $fileName = $first = $last = $mode = null;
                $parts = array_pad(preg_split('/\s+/', $chess, 4), 3, null);
                $fileName = $parts[0];
                if (preg_match('/^(begin|end|\d+[wb])(?:-(begin|end|\d+[wb]))?$/', $parts[1], $matches)) {
                    $first = $this->getPlyFromMove($matches[1]);
                    $last = isset($matches[2]) ? $this->getPlyFromMove($matches[2]) : $first;
                } else { // default values
                    $first = "begin";
                    $last = "end";
                }
                if (preg_match('/moves|diagram|all/', $parts[2])) {
                    $mode = $parts[2];
                } else { // default value
                    $mode = "all";
                }
                $path = $this->yellow->system->get("chessDirectory");
                $game = $this->getGameFromPgn($path.$fileName);
                if ($game!==false) {
                    if ($mode!=="moves") {
                        if ($last==="begin") {
                            $plyNumber = 0;
                        } elseif ($last==="end") {
                            $plyNumber = null;
                        } else {
                            $plyNumber = $last+1;
                        }
                        $position = $this->getPositionFromGame($game['moves'], $plyNumber, isset($game['FEN']) ? $game['FEN'] : null);
                    }
                    if ($mode!=="diagram" && !$position['errors']) {
                        $output .= $this->outputMoves($game, $first, $last);
                    }
                } else {
                    $errors[] = "Missing ".htmlspecialchars($fileName);
                }
            } else { // FEN
                $position = $this->getPositionFromFen($chess);
            }

            if (!$errors) {
                if (!isset($mode) || $mode!=="moves") {
                    if (!$position['errors']) {
                        $chessPieceList = $this->yellow->system->get("chessPieceList");
                        if ($chessPieceList) {
                            $pieceTable = [];
                            $columnIndexes = "abcdefgh";
                            $rowIndexes = "87654321";
                            for ($x=0; $x<=7; $x++) {
                                for ($y=7; $y>=0; $y--) {
                                    if ($position['board'][$y][$x]) {
                                        $pieceTable[$position['board'][$y][$x]][] = $columnIndexes[$x].$rowIndexes[$y];
                                    }
                                }
                            }
                            $colorNames = preg_split('/\s*,\s*/', $this->yellow->language->getText("chessColors"));
                            $pieceNames = preg_split('/\s*,\s*/', $this->yellow->language->getText("chessPieces"));
                            $pieceNamesPlural = preg_split('/\s*,\s*/', $this->yellow->language->getText("chessPiecesPlural"));
                            $pieceList = "";
                            foreach ([str_split('KQRBNP'), str_split('kqrbnp')] as $colorKey=>$color) {
                                $pieceList .= $colorNames[$colorKey].": ";
                                $colorList = [];
                                foreach ($color as $pieceKey=>$piece) {
                                    if (isset($pieceTable[$piece])) {
                                        $colorList[] = count($pieceTable[$piece])==1 ? $pieceNames[$pieceKey]." ".$pieceTable[$piece][0] : $pieceNamesPlural[$pieceKey]." ".implode(", ", $pieceTable[$piece]);
                                    }
                                }
                                $pieceList .= implode("; ", $colorList).". ";
                            }
                            $pieceList .= str_replace("@color", !$position['active'] ? $colorNames[0] : $colorNames[1], $this->yellow->language->getText("chessToMove"));
                        } else {
                            $pieceList = isset($mode) ? $this->getFenFromPosition($position) : $chess;
                        }
                        if (empty($width)) $width = $this->yellow->system->get("chessWidth");
    	            $svg = $this->drawBoardFromPosition($position);
                        $output .= "<img src=\"data:image/svg+xml;base64,".base64_encode($svg)."\"";
                        $output .= " width=\"".htmlspecialchars($width)."\" height=\"".htmlspecialchars($width)."\"";
                        $output .= " alt=\"".htmlspecialchars($pieceList)."\" title=\"".htmlspecialchars($pieceList)."\"";
                        if (!empty($style)) $output .= " class=\"".htmlspecialchars($style)."\"";
                        $output .= " />\n";
                    } else {
                        $output .= "<strong>[chess: ".implode(", ", $position['errors'])."]</strong>";
                    }
                }
            } else {
		$output .= "<strong>[chess: ".implode(", ", $errors)."]</strong>";
            }
        }
        return $output;
    }

    private function getPositionFromFen($fen) {
        $parts = explode(" ", $fen);
        $position = [];
        $position['board'] = $position['active'] = $position['castling'] = $position['halfmoves'] = $position['fullmoves'] = null;
        $position['enpassant'] = [ null, null ];
        $position['errors'] = [];

        // board
        $position['board'] = array_fill(0, 8, array_fill(0, 8, null));
        $currentColumn = $currentRow = 0;
        $invalidChar = false;
        for ($i = 0; $i<strlen($parts[0]); $i++) {
            if (strpos("12345678", $parts[0][$i])!==false) {
                for ($j=1; $j<=$parts[0][$i]; $j++) {
                    $currentColumn += 1;
                }
            } elseif (strpos("BKNPQRbknpqr", $parts[0][$i])!==false) {
                $position['board'][$currentRow][$currentColumn] = $parts[0][$i];
                $currentColumn += 1;
            } elseif ($parts[0][$i]==="/") {
                if ($currentColumn<8) break;
                $currentColumn = 0;
                $currentRow += 1;
            } else {
                $invalidChar = true; break;
            }
            if ($currentRow>7 || $currentColumn>8) break;
        }
        if ($invalidChar) {
            $position['errors'][] = "Invalid char at pos $i";
        } elseif ($currentRow>7) {
            $position['errors'][] = "Too many rows at pos $i";
        } elseif ($currentColumn>8) {
            $position['errors'][] = "Too many columns at pos $i";
        } elseif ($currentColumn<8) {
            $position['errors'][] = "Too few columns at pos $i";
        } elseif ($currentRow<7) {
            $position['errors'][] = "Too few rows at pos $i";
        }

        // other fields
        $fields = [
            ['active', '/^[bw]$/'],
            ['castling', '/^K?Q?k?q?$/'],
            ['enpassant', '/^[a-h][36]$/'],
            ['halfmoves', '/^[\d]+$/'],
            ['fullmoves', '/^[\d]+$/']
        ];

        foreach (array_slice($parts, 1) as $index=>$part) {
            if (preg_match($fields[$index][1], $part)) {
                $position[$fields[$index][0]] = $part;
            } elseif ($part!=="-" || $index==0) {
                $position['errors'][] = "Invalid char in field {$fields[$index][0]}";
            }
        }

        $letToNum = [ 'a'=>0, 'b'=>1, 'c'=>2, 'd'=>3, 'e'=>4, 'f'=>5, 'g'=>6, 'h'=>7, ''=>null ];
        $numToNum = [ '1'=>7, '2'=>6, '3'=>5, '4'=>4, '5'=>3, '6'=>2, '7'=>1, '8'=>0, ''=>null ];
        $position['active'] = $position['active']=='w';
        $position['castling'] = array_filter(['K'=>true, 'Q'=>true, 'k'=>true, 'q'=>true ], function($key) use ($position) { return strpos($position['castling'], $key)!==false; }, ARRAY_FILTER_USE_KEY);
        $position['enpassant'] = $position['enpassant']=='-' ? [ null, null ] : [ $numToNum[$position['enpassant'][1]], $letToNum[$position['enpassant'][0]]];

        return $position;
    }

    // Draw SVG board
    private function drawBoardFromPosition($position) {
        $svgBoard = '<svg viewBox="39 39 403 403" version="1.1" xmlns="http://www.w3.org/2000/svg">
<style>.coord { font: 11px sans-serif; }</style>
<desc>SVG Chess Board</desc>
<defs>
<desc>This SVG contains wikimedia SVG chess pieces (CC BY-SA 3.0) from https://commons.wikimedia.org/wiki/Category:SVG_chess_pieces</desc>
<g id="b">
  <g style="opacity:1; fill:none; fill-rule:evenodd; fill-opacity:1; stroke:#000000; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round; stroke-miterlimit:4; stroke-dasharray:none; stroke-opacity:1;">
    <g style="fill:#000000; stroke:#000000; stroke-linecap:butt;">
      <path d="M 9,36 C 12.39,35.03 19.11,36.43 22.5,34 C 25.89,36.43 32.61,35.03 36,36 C 36,36 37.65,36.54 39,38 C 38.32,38.97 37.35,38.99 36,38.5 C 32.61,37.53 25.89,38.96 22.5,37.5 C 19.11,38.96 12.39,37.53 9,38.5 C 7.646,38.99 6.677,38.97 6,38 C 7.354,36.06 9,36 9,36 z"/>
      <path d="M 15,32 C 17.5,34.5 27.5,34.5 30,32 C 30.5,30.5 30,30 30,30 C 30,27.5 27.5,26 27.5,26 C 33,24.5 33.5,14.5 22.5,10.5 C 11.5,14.5 12,24.5 17.5,26 C 17.5,26 15,27.5 15,30 C 15,30 14.5,30.5 15,32 z"/>
      <path d="M 25 8 A 2.5 2.5 0 1 1  20,8 A 2.5 2.5 0 1 1  25 8 z"/>
    </g>
    <path d="M 17.5,26 L 27.5,26 M 15,30 L 30,30 M 22.5,15.5 L 22.5,20.5 M 20,18 L 25,18" style="fill:none; stroke:#ffffff; stroke-linejoin:miter;"/>
  </g>
</g>
<g id="B">
  <g style="opacity:1; fill:none; fill-rule:evenodd; fill-opacity:1; stroke:#000000; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round; stroke-miterlimit:4; stroke-dasharray:none; stroke-opacity:1;">
    <g style="fill:#ffffff; stroke:#000000; stroke-linecap:butt;">
      <path d="M 9,36 C 12.39,35.03 19.11,36.43 22.5,34 C 25.89,36.43 32.61,35.03 36,36 C 36,36 37.65,36.54 39,38 C 38.32,38.97 37.35,38.99 36,38.5 C 32.61,37.53 25.89,38.96 22.5,37.5 C 19.11,38.96 12.39,37.53 9,38.5 C 7.646,38.99 6.677,38.97 6,38 C 7.354,36.06 9,36 9,36 z"/>
      <path d="M 15,32 C 17.5,34.5 27.5,34.5 30,32 C 30.5,30.5 30,30 30,30 C 30,27.5 27.5,26 27.5,26 C 33,24.5 33.5,14.5 22.5,10.5 C 11.5,14.5 12,24.5 17.5,26 C 17.5,26 15,27.5 15,30 C 15,30 14.5,30.5 15,32 z"/>
      <path d="M 25 8 A 2.5 2.5 0 1 1  20,8 A 2.5 2.5 0 1 1  25 8 z"/>
    </g>
    <path d="M 17.5,26 L 27.5,26 M 15,30 L 30,30 M 22.5,15.5 L 22.5,20.5 M 20,18 L 25,18" style="fill:none; stroke:#000000; stroke-linejoin:miter;"/>
  </g>
</g>
<g id="k">
  <g style="fill:none; fill-opacity:1; fill-rule:evenodd; stroke:#000000; stroke-width:1.5; stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:4; stroke-dasharray:none; stroke-opacity:1;">
    <path d="M 22.5,11.63 L 22.5,6" style="fill:none; stroke:#000000; stroke-linejoin:miter;" id="path6570"/>
    <path d="M 22.5,25 C 22.5,25 27,17.5 25.5,14.5 C 25.5,14.5 24.5,12 22.5,12 C 20.5,12 19.5,14.5 19.5,14.5 C 18,17.5 22.5,25 22.5,25" style="fill:#000000;fill-opacity:1; stroke-linecap:butt; stroke-linejoin:miter;"/>
    <path d="M 11.5,37 C 17,40.5 27,40.5 32.5,37 L 32.5,30 C 32.5,30 41.5,25.5 38.5,19.5 C 34.5,13 25,16 22.5,23.5 L 22.5,27 L 22.5,23.5 C 19,16 9.5,13 6.5,19.5 C 3.5,25.5 11.5,29.5 11.5,29.5 L 11.5,37 z " style="fill:#000000; stroke:#000000;"/>
    <path d="M 20,8 L 25,8" style="fill:none; stroke:#000000; stroke-linejoin:miter;"/>
    <path d="M 32,29.5 C 32,29.5 40.5,25.5 38.03,19.85 C 34.15,14 25,18 22.5,24.5 L 22.51,26.6 L 22.5,24.5 C 20,18 9.906,14 6.997,19.85 C 4.5,25.5 11.85,28.85 11.85,28.85" style="fill:none; stroke:#ffffff;"/>
    <path d="M 11.5,30 C 17,27 27,27 32.5,30 M 11.5,33.5 C 17,30.5 27,30.5 32.5,33.5 M 11.5,37 C 17,34 27,34 32.5,37" style="fill:none; stroke:#ffffff;"/>
  </g>
</g>
<g id="K">
  <g style="fill:none; fill-opacity:1; fill-rule:evenodd; stroke:#000000; stroke-width:1.5; stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:4; stroke-dasharray:none; stroke-opacity:1;">
    <path d="M 22.5,11.63 L 22.5,6" style="fill:none; stroke:#000000; stroke-linejoin:miter;"/>
    <path d="M 20,8 L 25,8" style="fill:none; stroke:#000000; stroke-linejoin:miter;"/>
    <path d="M 22.5,25 C 22.5,25 27,17.5 25.5,14.5 C 25.5,14.5 24.5,12 22.5,12 C 20.5,12 19.5,14.5 19.5,14.5 C 18,17.5 22.5,25 22.5,25" style="fill:#ffffff; stroke:#000000; stroke-linecap:butt; stroke-linejoin:miter;"/>
    <path d="M 11.5,37 C 17,40.5 27,40.5 32.5,37 L 32.5,30 C 32.5,30 41.5,25.5 38.5,19.5 C 34.5,13 25,16 22.5,23.5 L 22.5,27 L 22.5,23.5 C 19,16 9.5,13 6.5,19.5 C 3.5,25.5 11.5,29.5 11.5,29.5 L 11.5,37 z " style="fill:#ffffff; stroke:#000000;"/>
    <path d="M 11.5,30 C 17,27 27,27 32.5,30" style="fill:none; stroke:#000000;"/>
    <path d="M 11.5,33.5 C 17,30.5 27,30.5 32.5,33.5" style="fill:none; stroke:#000000;"/>
    <path d="M 11.5,37 C 17,34 27,34 32.5,37" style="fill:none; stroke:#000000;"/>
  </g>
</g>
<g id="n">
  <g style="opacity:1; fill:none; fill-opacity:1; fill-rule:evenodd; stroke:#000000; stroke-width:1.5; stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:4; stroke-dasharray:none; stroke-opacity:1;">
    <path d="M 22,10 C 32.5,11 38.5,18 38,39 L 15,39 C 15,30 25,32.5 23,18" style="fill:#000000; stroke:#000000;"/>
    <path d="M 24,18 C 24.38,20.91 18.45,25.37 16,27 C 13,29 13.18,31.34 11,31 C 9.958,30.06 12.41,27.96 11,28 C 10,28 11.19,29.23 10,30 C 9,30 5.997,31 6,26 C 6,24 12,14 12,14 C 12,14 13.89,12.1 14,10.5 C 13.27,9.506 13.5,8.5 13.5,7.5 C 14.5,6.5 16.5,10 16.5,10 L 18.5,10 C 18.5,10 19.28,8.008 21,7 C 22,7 22,10 22,10" style="fill:#000000; stroke:#000000;"/>
    <path d="M 9.5 25.5 A 0.5 0.5 0 1 1 8.5,25.5 A 0.5 0.5 0 1 1 9.5 25.5 z" style="fill:#ffffff; stroke:#ffffff;"/>
    <path d="M 15 15.5 A 0.5 1.5 0 1 1  14,15.5 A 0.5 1.5 0 1 1  15 15.5 z" transform="matrix(0.866,0.5,-0.5,0.866,9.693,-5.173)" style="fill:#ffffff; stroke:#ffffff;"/>
    <path d="M 24.55,10.4 L 24.1,11.85 L 24.6,12 C 27.75,13 30.25,14.49 32.5,18.75 C 34.75,23.01 35.75,29.06 35.25,39 L 35.2,39.5 L 37.45,39.5 L 37.5,39 C 38,28.94 36.62,22.15 34.25,17.66 C 31.88,13.17 28.46,11.02 25.06,10.5 L 24.55,10.4 z " style="fill:#ffffff; stroke:none;"/>
  </g>
</g>
<g id="N">
  <g style="opacity:1; fill:none; fill-opacity:1; fill-rule:evenodd; stroke:#000000; stroke-width:1.5; stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:4; stroke-dasharray:none; stroke-opacity:1;">
    <path d="M 22,10 C 32.5,11 38.5,18 38,39 L 15,39 C 15,30 25,32.5 23,18" style="fill:#ffffff; stroke:#000000;"/>
    <path d="M 24,18 C 24.38,20.91 18.45,25.37 16,27 C 13,29 13.18,31.34 11,31 C 9.958,30.06 12.41,27.96 11,28 C 10,28 11.19,29.23 10,30 C 9,30 5.997,31 6,26 C 6,24 12,14 12,14 C 12,14 13.89,12.1 14,10.5 C 13.27,9.506 13.5,8.5 13.5,7.5 C 14.5,6.5 16.5,10 16.5,10 L 18.5,10 C 18.5,10 19.28,8.008 21,7 C 22,7 22,10 22,10" style="fill:#ffffff; stroke:#000000;"/>
    <path d="M 9.5 25.5 A 0.5 0.5 0 1 1 8.5,25.5 A 0.5 0.5 0 1 1 9.5 25.5 z" style="fill:#000000; stroke:#000000;"/>
    <path d="M 15 15.5 A 0.5 1.5 0 1 1  14,15.5 A 0.5 1.5 0 1 1  15 15.5 z" transform="matrix(0.866,0.5,-0.5,0.866,9.693,-5.173)" style="fill:#000000; stroke:#000000;"/>
  </g>
</g>
<g id="p">
  <path d="M 22,9 C 19.79,9 18,10.79 18,13 C 18,13.89 18.29,14.71 18.78,15.38 C 16.83,16.5 15.5,18.59 15.5,21 C 15.5,23.03 16.44,24.84 17.91,26.03 C 14.91,27.09 10.5,31.58 10.5,39.5 L 33.5,39.5 C 33.5,31.58 29.09,27.09 26.09,26.03 C 27.56,24.84 28.5,23.03 28.5,21 C 28.5,18.59 27.17,16.5 25.22,15.38 C 25.71,14.71 26,13.89 26,13 C 26,10.79 24.21,9 22,9 z " style="opacity:1; fill:#000000; fill-opacity:1; fill-rule:nonzero; stroke:#000000; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:miter; stroke-miterlimit:4; stroke-dasharray:none; stroke-opacity:1;"/>
</g>
<g id="P">
  <path d="M 22,9 C 19.79,9 18,10.79 18,13 C 18,13.89 18.29,14.71 18.78,15.38 C 16.83,16.5 15.5,18.59 15.5,21 C 15.5,23.03 16.44,24.84 17.91,26.03 C 14.91,27.09 10.5,31.58 10.5,39.5 L 33.5,39.5 C 33.5,31.58 29.09,27.09 26.09,26.03 C 27.56,24.84 28.5,23.03 28.5,21 C 28.5,18.59 27.17,16.5 25.22,15.38 C 25.71,14.71 26,13.89 26,13 C 26,10.79 24.21,9 22,9 z " style="opacity:1; fill:#ffffff; fill-opacity:1; fill-rule:nonzero; stroke:#000000; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:miter; stroke-miterlimit:4; stroke-dasharray:none; stroke-opacity:1;"/>
</g>
<g id="q">
  <g style="opacity:1; fill:000000; fill-opacity:1; fill-rule:evenodd; stroke:#000000; stroke-width:1.5; stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:4; stroke-dasharray:none; stroke-opacity:1;">
    <g style="fill:#000000; stroke:none;">
      <circle cx="6" cy="12" r="2.75"/>
      <circle cx="14" cy="9" r="2.75"/>
      <circle cx="22.5" cy="8" r="2.75"/>
      <circle cx="31" cy="9" r="2.75"/>
      <circle cx="39" cy="12" r="2.75"/>
    </g>
    <path d="M 9,26 C 17.5,24.5 30,24.5 36,26 L 38.5,13.5 L 31,25 L 30.7,10.9 L 25.5,24.5 L 22.5,10 L 19.5,24.5 L 14.3,10.9 L 14,25 L 6.5,13.5 L 9,26 z" style="stroke-linecap:butt; stroke:#000000;"/>
    <path d="M 9,26 C 9,28 10.5,28 11.5,30 C 12.5,31.5 12.5,31 12,33.5 C 10.5,34.5 10.5,36 10.5,36 C 9,37.5 11,38.5 11,38.5 C 17.5,39.5 27.5,39.5 34,38.5 C 34,38.5 35.5,37.5 34,36 C 34,36 34.5,34.5 33,33.5 C 32.5,31 32.5,31.5 33.5,30 C 34.5,28 36,28 36,26 C 27.5,24.5 17.5,24.5 9,26 z" style="stroke-linecap:butt;"/>
    <path d="M 11,38.5 A 35,35 1 0 0 34,38.5" style="fill:none; stroke:#000000; stroke-linecap:butt;"/>
    <path d="M 11,29 A 35,35 1 0 1 34,29" style="fill:none; stroke:#ffffff;"/>
    <path d="M 12.5,31.5 L 32.5,31.5" style="fill:none; stroke:#ffffff;"/>
    <path d="M 11.5,34.5 A 35,35 1 0 0 33.5,34.5" style="fill:none; stroke:#ffffff;"/>
    <path d="M 10.5,37.5 A 35,35 1 0 0 34.5,37.5" style="fill:none; stroke:#ffffff;"/>
  </g>
</g>
<g id="Q">
  <g style="opacity:1; fill:#ffffff; fill-opacity:1; fill-rule:evenodd; stroke:#000000; stroke-width:1.5; stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:4; stroke-dasharray:none; stroke-opacity:1;">
    <path d="M 9 13 A 2 2 0 1 1  5,13 A 2 2 0 1 1  9 13 z" transform="translate(-1,-1)"/>
    <path d="M 9 13 A 2 2 0 1 1  5,13 A 2 2 0 1 1  9 13 z" transform="translate(15.5,-5.5)"/>
    <path d="M 9 13 A 2 2 0 1 1  5,13 A 2 2 0 1 1  9 13 z" transform="translate(32,-1)"/>
    <path d="M 9 13 A 2 2 0 1 1  5,13 A 2 2 0 1 1  9 13 z" transform="translate(7,-4.5)"/>
    <path d="M 9 13 A 2 2 0 1 1  5,13 A 2 2 0 1 1  9 13 z" transform="translate(24,-4)"/>
    <path d="M 9,26 C 17.5,24.5 30,24.5 36,26 L 38,14 L 31,25 L 31,11 L 25.5,24.5 L 22.5,9.5 L 19.5,24.5 L 14,10.5 L 14,25 L 7,14 L 9,26 z " style="stroke-linecap:butt;"/>
    <path d="M 9,26 C 9,28 10.5,28 11.5,30 C 12.5,31.5 12.5,31 12,33.5 C 10.5,34.5 10.5,36 10.5,36 C 9,37.5 11,38.5 11,38.5 C 17.5,39.5 27.5,39.5 34,38.5 C 34,38.5 35.5,37.5 34,36 C 34,36 34.5,34.5 33,33.5 C 32.5,31 32.5,31.5 33.5,30 C 34.5,28 36,28 36,26 C 27.5,24.5 17.5,24.5 9,26 z " style="stroke-linecap:butt;"/>
    <path d="M 11.5,30 C 15,29 30,29 33.5,30" style="fill:none;"/>
    <path d="M 12,33.5 C 18,32.5 27,32.5 33,33.5" style="fill:none;"/>
  </g>
</g>
<g id="r">
  <g style="opacity:1; fill:000000; fill-opacity:1; fill-rule:evenodd; stroke:#000000; stroke-width:1.5; stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:4; stroke-dasharray:none; stroke-opacity:1;">
    <path d="M 9,39 L 36,39 L 36,36 L 9,36 L 9,39 z " style="stroke-linecap:butt;"/>
    <path d="M 12.5,32 L 14,29.5 L 31,29.5 L 32.5,32 L 12.5,32 z " style="stroke-linecap:butt;"/>
    <path d="M 12,36 L 12,32 L 33,32 L 33,36 L 12,36 z " style="stroke-linecap:butt;"/>
    <path d="M 14,29.5 L 14,16.5 L 31,16.5 L 31,29.5 L 14,29.5 z " style="stroke-linecap:butt;stroke-linejoin:miter;"/>
    <path d="M 14,16.5 L 11,14 L 34,14 L 31,16.5 L 14,16.5 z " style="stroke-linecap:butt;"/>
    <path d="M 11,14 L 11,9 L 15,9 L 15,11 L 20,11 L 20,9 L 25,9 L 25,11 L 30,11 L 30,9 L 34,9 L 34,14 L 11,14 z " style="stroke-linecap:butt;"/>
    <path d="M 12,35.5 L 33,35.5 L 33,35.5" style="fill:none; stroke:#ffffff; stroke-width:1; stroke-linejoin:miter;"/>
    <path d="M 13,31.5 L 32,31.5" style="fill:none; stroke:#ffffff; stroke-width:1; stroke-linejoin:miter;"/>
    <path d="M 14,29.5 L 31,29.5" style="fill:none; stroke:#ffffff; stroke-width:1; stroke-linejoin:miter;"/>
    <path d="M 14,16.5 L 31,16.5" style="fill:none; stroke:#ffffff; stroke-width:1; stroke-linejoin:miter;"/>
    <path d="M 11,14 L 34,14" style="fill:none; stroke:#ffffff; stroke-width:1; stroke-linejoin:miter;"/>
  </g>
</g>
<g id="R">
  <g style="opacity:1; fill:#ffffff; fill-opacity:1; fill-rule:evenodd; stroke:#000000; stroke-width:1.5; stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:4; stroke-dasharray:none; stroke-opacity:1;">
    <path d="M 9,39 L 36,39 L 36,36 L 9,36 L 9,39 z " style="stroke-linecap:butt;"/>
    <path d="M 12,36 L 12,32 L 33,32 L 33,36 L 12,36 z " style="stroke-linecap:butt;"/>
    <path d="M 11,14 L 11,9 L 15,9 L 15,11 L 20,11 L 20,9 L 25,9 L 25,11 L 30,11 L 30,9 L 34,9 L 34,14" style="stroke-linecap:butt;"/>
    <path d="M 34,14 L 31,17 L 14,17 L 11,14"/>
    <path d="M 31,17 L 31,29.5 L 14,29.5 L 14,17" style="stroke-linecap:butt; stroke-linejoin:miter;"/>
    <path d="M 31,29.5 L 32.5,32 L 12.5,32 L 14,29.5"/>
    <path d="M 11,14 L 34,14" style="fill:none; stroke:#000000; stroke-linejoin:miter;"/>
  </g>
</g>
</defs>
<g id="board" fill="none" stroke="#000"><rect x="40.5" y="40.5" width="400" height="400" fill="#fff"/><rect x="40.5" y="90.5" width="50" height="50" fill="#ccc"/><rect x="40.5" y="190.5" width="50" height="50" fill="#ccc"/><rect x="40.5" y="290.5" width="50" height="50" fill="#ccc"/><rect x="40.5" y="390.5" width="50" height="50" fill="#ccc"/><rect x="90.5" y="40.5" width="50" height="50" fill="#ccc"/><rect x="90.5" y="140.5" width="50" height="50" fill="#ccc"/><rect x="90.5" y="240.5" width="50" height="50" fill="#ccc"/><rect x="90.5" y="340.5" width="50" height="50" fill="#ccc"/><rect x="140.5" y="90.5" width="50" height="50" fill="#ccc"/><rect x="140.5" y="190.5" width="50" height="50" fill="#ccc"/><rect x="140.5" y="290.5" width="50" height="50" fill="#ccc"/><rect x="140.5" y="390.5" width="50" height="50" fill="#ccc"/><rect x="190.5" y="40.5" width="50" height="50" fill="#ccc"/><rect x="190.5" y="140.5" width="50" height="50" fill="#ccc"/><rect x="190.5" y="240.5" width="50" height="50" fill="#ccc"/><rect x="190.5" y="340.5" width="50" height="50" fill="#ccc"/><rect x="240.5" y="90.5" width="50" height="50" fill="#ccc"/><rect x="240.5" y="190.5" width="50" height="50" fill="#ccc"/><rect x="240.5" y="290.5" width="50" height="50" fill="#ccc"/><rect x="240.5" y="390.5" width="50" height="50" fill="#ccc"/><rect x="290.5" y="40.5" width="50" height="50" fill="#ccc"/><rect x="290.5" y="140.5" width="50" height="50" fill="#ccc"/><rect x="290.5" y="240.5" width="50" height="50" fill="#ccc"/><rect x="290.5" y="340.5" width="50" height="50" fill="#ccc"/><rect x="340.5" y="90.5" width="50" height="50" fill="#ccc"/><rect x="340.5" y="190.5" width="50" height="50" fill="#ccc"/><rect x="340.5" y="290.5" width="50" height="50" fill="#ccc"/><rect x="340.5" y="390.5" width="50" height="50" fill="#ccc"/><rect x="390.5" y="40.5" width="50" height="50" fill="#ccc"/><rect x="390.5" y="140.5" width="50" height="50" fill="#ccc"/><rect x="390.5" y="240.5" width="50" height="50" fill="#ccc"/><rect x="390.5" y="340.5" width="50" height="50" fill="#ccc"/></g>
<g id="pieces">';

        $columnWidth = $columnRow = 50;
        foreach ($position['board'] as $currentRow=>$row) {
            foreach ($row as $currentColumn=>$cell) {
                if ($cell!==null) {
                    $x = 43.5 + $currentColumn*$columnWidth;
                    $y = 44.5 + $currentRow*$columnRow;
                    $svgBoard .= "<use xmlns:xlink=\"http://www.w3.org/1999/xlink\" xlink:href=\"#{$cell}\" href=\"#{$cell}\" x=\"{$x}\" y=\"{$y}\"/>";
                }
            }
        }
        $svgBoard .= "</g>\n";
        // dots
        if ($this->yellow->system->get("chessShowDots")) {
            $whiteToMove = !$position['active'];
            $svgBoard .= "<circle id=\"active\" cx=\"432.5\" cy=\"".($whiteToMove ? "398.5" : "48.5")."\" r=\"3.5\" stroke=\"#000000\" stroke-width=\"1\" fill=\"".($whiteToMove ? "#ffffff" : "#000000")."\" />\n";
        }
        // coordinates
        if ($this->yellow->system->get("chessShowCoordinates")) {
            foreach (str_split('ABCDEFGH') as $index=>$letter) {
                $svgBoard .= "<text class=\"coord\" x=\"".(79+$index*$columnWidth)."\" y=\"437\">{$letter}</text>\n";
            }
            foreach (str_split('12345678') as $index=>$number) {
                $svgBoard .= "<text class=\"coord\" x=\"43\" y=\"".(404-$index*$columnWidth)."\">{$number}</text>\n";
            }
        }
        $svgBoard .= "</svg>";
        return $svgBoard;
    }

    // Calculate game position
    private function getPositionFromGame($moves, $plyNumber, $startingFen) {
        $plyNumber = $plyNumber===null ? count($moves)-1 : $plyNumber;
        $plyNumber = min($plyNumber, count($moves)-1);
        $startingFen = $startingFen==null ? "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1" : $startingFen;
        $position = $this->getPositionFromFen($startingFen);
        $position['errors'] = [];

        $letToNum = [ 'a'=>0, 'b'=>1, 'c'=>2, 'd'=>3, 'e'=>4, 'f'=>5, 'g'=>6, 'h'=>7, ''=>null ];
        $numToNum = [ '1'=>7, '2'=>6, '3'=>5, '4'=>4, '5'=>3, '6'=>2, '7'=>1, '8'=>0, ''=>null ];

        foreach (array_slice($moves, 0, $plyNumber) as $move) {
            $piece = $capture = $promotion = $check = $castling = null;
            $disambiguate = $destination = [ null, null ];
            if (preg_match('/^([RNBQK])([a-h])?([1-8])?(x)?([a-h])([1-8])([+#])?$/', $move, $matches)) {
                $piece = $matches[1];
                $disambiguate = [ $numToNum[$matches[3]], $letToNum[$matches[2]] ];
                $capture = $matches[4];
                $destination = [ $numToNum[$matches[6]], $letToNum[$matches[5]] ];
                //$check = empty($matches[7]) ? "" : $matches[7];
            } elseif (preg_match('/^(?:([a-h])(x))?([a-h])([1-8])(?:(=)([RNBQ]))?([+#])?$/', $move, $matches)) {
                $piece = 'P';
                $disambiguate = [ null, $letToNum[$matches[1]] ];
                $capture = $matches[2];
                $destination = [ $numToNum[$matches[4]], $letToNum[$matches[3]] ];
                $promotion = empty($matches[6]) ? "" : $matches[6];
                //$check = empty($matches[7]) ? "" : $matches[7];
            } elseif (preg_match('/^O-O(-O)?([+#])?$/', $move, $matches)) {
                $castling = empty($matches[1]) ? 'K' : 'Q';
                //$check = empty($matches[2]) ? "" : $matches[2];
            }

            // executions of moves
            if ($castling) { // no validity check
                $castlingRow = $position['active'] ? 7 : 0;
                $castlingColumns = $castling=='Q' ? [4, 2, 0, 3] : [4, 6, 7, 5];
                $position['board'][$castlingRow][$castlingColumns[1]] = $position['board'][$castlingRow][$castlingColumns[0]];
                $position['board'][$castlingRow][$castlingColumns[0]] = null;
                $position['board'][$castlingRow][$castlingColumns[3]] = $position['board'][$castlingRow][$castlingColumns[2]];
                $position['board'][$castlingRow][$castlingColumns[2]] = null;
                unset($position['castling'][$position['active'] ? 'K' : 'k'], $position['castling'][$position['active'] ? 'Q' : 'q']);
                $position['enpassant'] = [ null, null ];
                $position['halfmoves'] += 1;
            } else { // normal moves
                $existingPieces = $this->getExistingPieces($position['board'], $position['active'], $piece, $disambiguate);
                if (count($existingPieces)===0) {
                    $position['errors'][] = "No piece at move {$position['fullmoves']}".($position['active'] ? "w" : "b"); break;
                } elseif (count($existingPieces)===1) {
                    $actualPiece = $existingPieces[0];
                } else { // > 1
                    $targetingPieces = $this->getTargetingPieces($position['board'], $position['active'], $existingPieces, $destination, $capture, $piece);
                    if (count($targetingPieces)===0) {
                        $position['errors'][] = "No movable piece at move {$position['fullmoves']}".($position['active'] ? "w" : "b"); break;
                    } elseif (count($targetingPieces)===1) {
                        $actualPiece = $targetingPieces[0];
                    } else { // > 1
                        $legalPieces = $this->getLegalPieces($position['board'], $position['active'], $existingPieces, $destination);
                        if (count($legalPieces)===0) {
                           $position['errors'][] = "No legally movable piece at move {$position['fullmoves']}".($position['active'] ? "w" : "b"); break;
                        } elseif (count($legalPieces)===1) {
                            $actualPiece = $legalPieces[0];
                        } else {
                           $position['errors'][] = "Too many legally movable piece at move {$position['fullmoves']}".($position['active'] ? "w" : "b"); break;
                        }
                    }
                }
                // en passant capture
                if ($piece=='P' && $capture && $position['board'][$destination[0]][$destination[1]]==null) {
                    $position['board'][$position['active'] ? 2 : 5][$destination[1]] == null;
                }
                // update variables
                if ($piece=='K' && $actualPiece==[ $position['active'] ? 7 : 0, 4 ]) {
                    unset($position['castling'][$position['active'] ? 'K' : 'k'], $position['castling'][$position['active'] ? 'Q' : 'q'] );
                } elseif ($piece=='R' && $actualPiece==[ $position['active'] ? 7 : 0, 0 ]) {
                    unset($position['castling'][$position['active'] ? 'Q' : 'q'] );
                } elseif ($piece=='R' && $actualPiece==[ $position['active'] ? 7 : 0, 7 ]) {
                    unset($position['castling'][$position['active'] ? 'K' : 'k']);
                }
                if ($piece=='P' && $actualPiece[0]==($position['active'] ? 6 : 1) && $destination[0]==($position['active'] ? 4 : 3)) {
                    $position['enpassant'] = [ $destination[0]+($position['active'] ? 1 : -1), $destination[1] ];
                } else {
                    $position['enpassant'] = [ null, null ];
                }
                $position['halfmoves'] = $piece=='P' || $position['board'][$destination[0]][$destination[1]]!==null ? 0 : $position['halfmoves'] + 1;
                // update board
                $truePiece = $promotion ? $promotion : $piece;
                if (!$position['active']) $truePiece = strtolower($truePiece);
                $position['board'][$actualPiece[0]][$actualPiece[1]] = null;
                $position['board'][$destination[0]][$destination[1]] = $truePiece;
            }
            if ($position['active']) $position['fullmoves'] += 1;
            $position['active'] = !$position['active'];
        }
        $position['active'] = !$position['active']; // invert
        return $position;
    }

    // Find pieces in the chessboard
    private function getExistingPieces($board, $whiteToMove, $piece, $disambiguate = [ null, null ]) {
        $existingPieces = [];
        if (!$whiteToMove) $piece = strtolower($piece);
        for ($i=0; $i<8; $i++) {
            for ($j=0; $j<8; $j++) {
                if ($piece===$board[$i][$j] && ($disambiguate[0]===null || $disambiguate[0]===$i) && ($disambiguate[1]===null || $disambiguate[1]===$j))
                $existingPieces[] = [$i, $j];
            }
        }
        return $existingPieces;
    }

    // Find pieces which can arrive to a certain target
    private function getTargetingPieces($board, $whiteToMove, $existingPieces, $destination, $capture = null, $piece = null) {
        $targetingPieces = [];
        foreach ($existingPieces as $existingPiece) {
            if (!$piece) $piece = strtoupper($board[$existingPiece[0]][$existingPiece[1]]);
            if ($piece=='R' || $piece=='B' || $piece=='Q') {
                $differences = [];
                if ($piece=='R' || $piece=='Q') $differences = array_merge($differences, [ [0,1], [1,0], [0,-1], [-1,0] ]);
                if ($piece=='B' || $piece=='Q') $differences = array_merge($differences, [ [1,1], [1,-1], [-1,-1], [-1,1] ]);
                foreach ($differences as $difference) {
                    $actualCell = $existingPiece;
                    while ($actualCell[0]>=0 && $actualCell[0]<=7 && $actualCell[1]>=0 && $actualCell[1]<=7) {
                        if ($actualCell!==$existingPiece) { // avoid check on oneself
                            if ($actualCell==$destination) {
                                $targetingPieces[] = $existingPiece;
                                break 2; // continue with the following piece
                            }
                            if ($board[$actualCell[0]][$actualCell[1]]!==null) break; // found
                        }
                        $actualCell[0] += $difference[0]; // new cell
                        $actualCell[1] += $difference[1];
                    }
                }
            } elseif ($piece=='N' || $piece=='P' && $capture) {
                if ($piece=='N') {
                    $differences = [ [1,2], [2,1], [1,-2], [2,-1], [-1,2], [-2,1], [-1,-2], [-2,-1] ];
                } else { // pawn
                    $differences = [ [$whiteToMove ? -1 : 1, 1], [$whiteToMove ? -1 : 1, -1] ];
                }
                foreach ($differences as $difference) {
                    $actualCell[0] = $existingPiece[0]+$difference[0];
                    $actualCell[1] = $existingPiece[1]+$difference[1];
                    if ($actualCell==$destination) { // no matter out of range
                        $targetingPieces[] = $existingPiece;
                        break; // continue with the following piece
                    }
                }
            } elseif ($piece=='P' && !$capture) {
                $difference = [ $whiteToMove ? -1 : 1, 0 ];
                $firstPosition = $whiteToMove && $existingPiece[0]==6 || !$whiteToMove && $existingPiece[0]==1;
                $actualCell = $existingPiece;
                while ($actualCell[0]>=0 && $actualCell[0]<=7 && abs($actualCell[0]-$existingPiece[0])<=($firstPosition ? 2 : 1)) {
                    if ($actualCell!==$existingPiece) { // do not check oneself
                        if ($actualCell==$destination) {
                            $targetingPieces[] = $existingPiece;
                            break 2; // continue with the following piece
                        }
                        if ($board[$actualCell[0]][$actualCell[1]]!==null) break; // found
                    }
                    $actualCell[0] += $difference[0]; // new cell
                    $actualCell[1] += $difference[1]; // superfluous
                }
            }
        }
        return $targetingPieces;
    }

    // Find pieces which can arrive to a certain target without uncovering the king
    private function getLegalPieces($board, $whiteToMove, $existingPieces, $destination) {
        $legalPieces = [];
        $possibleAttackers = [];
        foreach ($whiteToMove ? ['q', 'r', 'b'] : ['Q', 'R', 'B'] as $attacker) {
            $possibleAttackers = array_merge($possibleAttackers, $this->getExistingPieces($board, $attacker));
        }
        $targetKing = $this->getExistingPieces($board, $whiteToMove ? 'K' : 'k')[0];
        foreach ($existingPieces as $existingPiece) {
            $tentativeBoard = $board;
            $pieceToMove = $tentativeBoard[$existingPiece[0]][$existingPiece[1]]; // any piece is good
            $tentativeBoard[$existingPiece[0]][$existingPiece[1]] = null;
            $tentativeBoard[$destination[0]][$destination[1]] = $pieceToMove;
            if (count($this->getTargetingPieces($tentativeBoard, !$whiteToMove, $possibleAttackers, $targetKing))==0) {
                $legalPieces[] = $existingPiece;
            }
        }
        return $legalPieces;
    }

    // Decode PGN file
    private function getGameFromPgn($fileName) {
        $lines = file($fileName);
	if ($lines!==false) {
            $game = [];
            $areTags = true;
            foreach ($lines as $line) {
                if ($areTags && preg_match('/^\s*\[\s*([A-Z][0-9A-Za-z_]*)\s*"(.*?)"\s*\]\s*$/', $line, $matches)) {
                    $game[strtolower($matches[1])] = $matches[2];
                } elseif (preg_match('/^\s*$/', $line)) {
                    if ($areTags) {
                        $areTags = false;
                        $game['moves'] = [];
                    } else {
                        break;
                    }
                } elseif ($line[0]=='%') {
                    continue;
                } else {
                    $game['moves'] = array_merge($game['moves'], preg_split('/\s+/', trim(preg_replace('/\d+\./', '', $line))));
                }
            }
            return $game;
        } else {
            return false;
        }
    }

    // Calculate ply
    private function getPlyFromMove($moveNumber) {
        if ($moveNumber==="begin" || $moveNumber==="end") {
            return $moveNumber;
        } elseif (preg_match('/^(\d+)([b|w])$/', $moveNumber, $matches)) {
            return $matches[1]*2+($matches[2]==="w" ? -2 : -1);
        }
    }

    // Write list of moves
    private function outputMoves($game, $from, $to) {
        $output = null;
        if ($from==="begin") {
            $tags = array_diff(array_keys($game), ['moves']);
            $interpolations = array_combine(array_map(function($tag) { return "@$tag"; }, $tags ), array_map(function($tag) use ($game) { return htmlspecialchars($game[$tag]); }, $tags));
            // Formatted fields
            foreach (['white', 'black'] as $color) {
                $interpolations["@{$color}f"] = implode(", ", array_map(function($name) use ($color) { $parts = explode(",", $name, 2); return isset($parts[1]) ? $parts[1]." ".$parts[0] : $parts[0]; }, explode(":", $interpolations["@{$color}"])));
            }
            $interpolations['@datef'] = strpos($interpolations['@date'], "?") ? "?" : $this->yellow->language->getDateFormatted(strtotime(str_replace(".", "-", $interpolations['@date'])), $this->yellow->language->getText("coreDateFormatMedium"));
            $interpolations['@resultf'] = str_replace("1/2", "½", $interpolations['@result']);
            $header = strtr($this->yellow->language->getText("chessHeader"), $interpolations);
            $output .= "<header class=\"chess-header\">".$header."</header>\n";
        }
        $moves = $game['moves'];
        $addResult = false;
        $from = $from==="begin" ? 0 : ($from==="end" ? count($moves)-1 : $from);
        $to = $to==="begin" ? -1 : ($to==="end" ? count($moves)-1 : $to);
        $to = min($to, count($moves)-1);
        $output .= "<p class=\"chess-moves\">";
        if ($to==count($moves)-1) {
            $to -= 1;
            $addResult = true;
        }
        if ($from % 2 ==1) $output .= (($from+1)/2)."...";
        for ($i=$from; $i<=$to; $i++) {
            if ($i%2==0) $output .= ($i/2+1).".";
            $output .= $moves[$i];
            $output .= " ";
        }
        if ($addResult) $output .= str_replace("1/2", "½", $game['result']);
        $output .= "</p>\n";
        return $output;
    }

    // Encode FEN
    private function getFenFromPosition($position) {
        $lines = [];
        foreach ($position['board'] as $row) {
            $line = "";
            foreach ($row as $cell) {
                $line .= $cell===null ? '1' : $cell;
            }
            $line = preg_replace_callback('/1+/', function($m) { return (string)strlen($m[0]); }, $line);
            $lines[] = $line;
        }
        $fen = implode(" ", [
            implode('/', $lines),
            $position['active'] ? "b" : "w", // inverted
            $position['castling']==[] ? '-' : implode('', array_keys($position['castling'])),
            $position['enpassant']==[ null, null ] ? '-' : "abcdefgh"[(int)$position['enpassant'][1]].(8-$position['enpassant'][0]),
            $position['halfmoves'],
            $position['fullmoves']-1
        ]);
        return $fen;
    }

    // Handle page content in HTML format
    public function onParseContentHtml($page, $text) {
        $output = null;
        $style = $this->yellow->system->get("chessMoveStyle");
        if ($style==="figurines" || $style==="letters" && $this->yellow->system->get("language")!=="en") {
            $translate = $this->setupTranslate();
            $patterns = [ // regex and index of the match to be translated
                ['/(\d+\.|\d\.\.\.|\s)([RNBQK])([a-h])?([1-8])?(x)?([a-h])([1-8])([+#])?\b/', 2],
                ['/(\d+\.|\d\.\.\.|\s)(?:([a-h])(x))?([a-h])([1-8])(?:(=)([RNBQ]))?([+#])?\b/', 7],
            ];
            $output = preg_replace_callback('/>([^<]+)</', function($matches) use ($patterns, $translate) {
                $translated = $matches[1];
                foreach ([0, 1] as $patternType) {
                    $translated = preg_replace_callback($patterns[$patternType][0], function($m) use ($translate, $patterns, $patternType) {
                        $color = !preg_match('/^\d+\.$/', $m[1]);
                        if (isset($m[$patterns[$patternType][1]])) $m[$patterns[$patternType][1]] = $translate[$color][$m[$patterns[$patternType][1]]];
                        return "<span class=\"chess-move\">".implode('', array_slice($m, 1))."</span>";
                    }, $translated);
                }
                return ">".$translated."<";
            }, $text);
        }
        return $output;
    }

    // Build translation table
    private function setupTranslate() {
        $translate = null;
        $style = $this->yellow->system->get("chessMoveStyle");
        if ($style==="figurines") {
              $extensionLocation = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("coreExtensionLocation");
            $pieceNames = preg_split('/\s*,\s*/', $this->yellow->language->getText("chessPieces"));
            $target = [];
            foreach ([0, 1] as $color) {
                $target[$color] = array_map(function($k, $v) use ($pieceNames, $color, $extensionLocation) { return "<img class=\"chess-figurine\" src=\"{$extensionLocation}chess-figurines/".$v."-".$color.".svg\" alt=\"".$pieceNames[$k]."\" title=\"".$pieceNames[$k]."\" />"; }, range(0,5), str_split('kqrbnp'));
            }
        } elseif ($style=="letters") {
            $target[0] = $target[1] = preg_split('/\s*,\s*/', $this->yellow->language->getText("chessPiecesInitial"));
        }
        foreach ([0, 1] as $color) {
            $translate[$color] = array_combine(str_split('KQRBNP'), $target[$color]);
        }
        return $translate;
    }

    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header") {
            $extensionLocation = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("coreExtensionLocation");
            $output .= "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$extensionLocation}chess.css\" />\n";
        }
        return $output;
    }

}
