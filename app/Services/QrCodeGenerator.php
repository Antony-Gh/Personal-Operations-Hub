<?php

namespace App\Services;

class QrCodeGenerator
{
    // GF(256) log and exp tables
    private static array $exp = [];

    private static array $log = [];

    private static bool $tablesInitialized = false;

    private static function initTables(): void
    {
        if (self::$tablesInitialized) {
            return;
        }

        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);

        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D; // Primitive polynomial x^8 + x^4 + x^3 + x^2 + 1
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }

        self::$tablesInitialized = true;
    }

    private static function gfMul(int $x, int $y): int
    {
        if ($x === 0 || $y === 0) {
            return 0;
        }

        return self::$exp[self::$log[$x] + self::$log[$y]];
    }

    private static function rsPolyMul(array $p1, array $p2): array
    {
        $result = array_fill(0, count($p1) + count($p2) - 1, 0);
        for ($i = 0; $i < count($p1); $i++) {
            for ($j = 0; $j < count($p2); $j++) {
                $result[$i + $j] ^= self::gfMul($p1[$i], $p2[$j]);
            }
        }

        return $result;
    }

    private static function rsGenerator(int $ecCount): array
    {
        $poly = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $poly = self::rsPolyMul($poly, [1, self::$exp[$i]]);
        }

        return $poly;
    }

    private static function rsEncode(array $data, int $ecCount): array
    {
        self::initTables();
        $gen = self::rsGenerator($ecCount);
        $remainder = array_merge($data, array_fill(0, $ecCount, 0));

        for ($i = 0; $i < count($data); $i++) {
            $coef = $remainder[$i];
            if ($coef !== 0) {
                for ($j = 0; $j < count($gen); $j++) {
                    $remainder[$i + $j] ^= self::gfMul($gen[$j], $coef);
                }
            }
        }

        return array_slice($remainder, count($data));
    }

    // Supported Versions: 1, 2, 3, 4 with Level M (or L fallback)
    // Version config: [version, size, totalCodewords, ecCodewords, dataCodewords, alignmentPos]
    private static array $versions = [
        1 => ['version' => 1, 'size' => 21, 'total' => 26, 'ec' => 10, 'data' => 16, 'align' => []],
        2 => ['version' => 2, 'size' => 25, 'total' => 44, 'ec' => 16, 'data' => 28, 'align' => [18]],
        3 => ['version' => 3, 'size' => 29, 'total' => 70, 'ec' => 26, 'data' => 44, 'align' => [22]],
        4 => ['version' => 4, 'size' => 33, 'total' => 100, 'ec' => 36, 'data' => 64, 'align' => [26]],
    ];

    /**
     * Generate 2D boolean matrix representing QR code (true = black/dark module).
     */
    public static function generateMatrix(string $text): array
    {
        $bytes = array_values(unpack('C*', $text));
        $dataLen = count($bytes);

        // Pick suitable version (Byte mode overhead: 4 bits mode + 8 bits count)
        $selectedVersion = null;
        foreach (self::$versions as $vInfo) {
            if ($dataLen <= $vInfo['data'] - 2) { // 2 bytes overhead
                $selectedVersion = $vInfo;
                break;
            }
        }

        if (! $selectedVersion) {
            $selectedVersion = self::$versions[4];
        }

        $size = $selectedVersion['size'];
        $dataCapacity = $selectedVersion['data'];
        $ecCount = $selectedVersion['ec'];

        // Bit stream construction (Byte Mode: 0100)
        $bits = [];
        // Mode indicator (4 bits: 0100)
        $bits = array_merge($bits, [0, 1, 0, 0]);
        // Character count indicator (8 bits)
        for ($i = 7; $i >= 0; $i--) {
            $bits[] = ($dataLen >> $i) & 1;
        }
        // Data bits
        foreach ($bytes as $b) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($b >> $i) & 1;
            }
        }

        // Terminator (up to 4 zeroes)
        $termLen = min(4, ($dataCapacity * 8) - count($bits));
        for ($i = 0; $i < $termLen; $i++) {
            $bits[] = 0;
        }

        // Pad to byte boundary
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        // Convert bits to data codewords
        $dataCodewords = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $val = 0;
            for ($j = 0; $j < 8; $j++) {
                $val = ($val << 1) | $bits[$i + $j];
            }
            $dataCodewords[] = $val;
        }

        // Pad codewords to capacity with 0xEC and 0x11
        $padBytes = [0xEC, 0x11];
        $p = 0;
        while (count($dataCodewords) < $dataCapacity) {
            $dataCodewords[] = $padBytes[$p % 2];
            $p++;
        }

        // Compute error correction codewords
        $ecCodewords = self::rsEncode($dataCodewords, $ecCount);

        // All codewords in order
        $allCodewords = array_merge($dataCodewords, $ecCodewords);

        // Matrix initialization: null = unallocated
        $matrix = array_fill(0, $size, array_fill(0, $size, null));
        $isFunction = array_fill(0, $size, array_fill(0, $size, false));

        $setFunc = function (int $r, int $c, bool $val) use (&$matrix, &$isFunction, $size): void {
            if ($r >= 0 && $r < $size && $c >= 0 && $c < $size) {
                $matrix[$r][$c] = $val;
                $isFunction[$r][$c] = true;
            }
        };

        // 1. Finder patterns (7x7) + Separators
        $placeFinder = function (int $top, int $left) use ($setFunc): void {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $row = $top + $r;
                    $col = $left + $c;
                    if ($r === -1 || $r === 7 || $c === -1 || $c === 7) {
                        $setFunc($row, $col, false); // Separator
                    } elseif ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) {
                        $setFunc($row, $col, true);
                    } else {
                        $setFunc($row, $col, false);
                    }
                }
            }
        };

        $placeFinder(0, 0);
        $placeFinder(0, $size - 7);
        $placeFinder($size - 7, 0);

        // 2. Alignment patterns (5x5) for Version >= 2
        foreach ($selectedVersion['align'] as $alignPos) {
            $centers = [
                [$alignPos, $alignPos],
                [6, $alignPos],
                [$alignPos, 6],
            ];
            foreach ($centers as [$ar, $ac]) {
                // Don't collide with finders
                if (($ar < 9 && $ac < 9) || ($ar < 9 && $ac > $size - 9) || ($ar > $size - 9 && $ac < 9)) {
                    continue;
                }
                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $isBlack = (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0));
                        $setFunc($ar + $r, $ac + $c, $isBlack);
                    }
                }
            }
        }

        // 3. Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $setFunc(6, $i, ($i % 2 === 0));
            $setFunc($i, 6, ($i % 2 === 0));
        }

        // 4. Dark module
        $setFunc(4 * $selectedVersion['version'] + 9, 8, true);

        // 5. Reserve Format info areas
        for ($i = 0; $i < 9; $i++) {
            if ($matrix[8][$i] === null) {
                $isFunction[8][$i] = true;
            }
            if ($matrix[$i][8] === null) {
                $isFunction[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $isFunction[8][$size - 1 - $i] = true;
            $isFunction[$size - 1 - $i][8] = true;
        }

        // 6. Data module placement
        $codewordBits = [];
        foreach ($allCodewords as $cw) {
            for ($b = 7; $b >= 0; $b--) {
                $codewordBits[] = ($cw >> $b) & 1;
            }
        }

        $bitIdx = 0;
        $totalBits = count($codewordBits);
        $dirUp = true;

        for ($rightCol = $size - 1; $rightCol > 0; $rightCol -= 2) {
            if ($rightCol === 6) {
                $rightCol--; // Skip vertical timing column
            }

            $rows = $dirUp ? range($size - 1, 0) : range(0, $size - 1);
            foreach ($rows as $r) {
                for ($colOffset = 0; $colOffset < 2; $colOffset++) {
                    $c = $rightCol - $colOffset;
                    if (! $isFunction[$r][$c]) {
                        $bit = ($bitIdx < $totalBits) ? $codewordBits[$bitIdx++] : 0;
                        // Apply Mask Pattern 0: (row + col) % 2 === 0
                        $mask = (($r + $c) % 2 === 0) ? 1 : 0;
                        $matrix[$r][$c] = (($bit ^ $mask) === 1);
                    }
                }
            }
            $dirUp = ! $dirUp;
        }

        // 7. Format Information for Mask 0, ECC Level M:
        // Format string bits: Level M (00) + Mask 0 (000) -> 00000
        // BCH error correction: 101010000010010 XOR 101010000010010 = 0x5412 XOR 0x0 = 0x5412
        // Pre-computed format bits for (Level M, Mask 0): 0b101010000010010
        $fmtBits = [1, 0, 1, 0, 1, 0, 0, 0, 0, 0, 1, 0, 0, 1, 0];

        // Format info around top-left finder
        $tlCoords = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5],
            [8, 7], [8, 8], [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        foreach ($tlCoords as $idx => [$r, $c]) {
            $matrix[$r][$c] = ($fmtBits[$idx] === 1);
        }

        // Split format info on bottom-left and top-right
        for ($i = 0; $i < 7; $i++) {
            $matrix[$size - 1 - $i][8] = ($fmtBits[$i] === 1);
        }
        for ($i = 7; $i < 15; $i++) {
            $matrix[8][$size - 15 + $i] = ($fmtBits[$i] === 1);
        }

        return $matrix;
    }

    /**
     * Render QR code as an SVG string.
     */
    public static function toSvg(string $text, int $targetSize = 220): string
    {
        $matrix = self::generateMatrix($text);
        $moduleCount = count($matrix);
        $quietZone = 4;
        $totalModules = $moduleCount + ($quietZone * 2);

        $rects = [];
        for ($r = 0; $r < $moduleCount; $r++) {
            for ($c = 0; $c < $moduleCount; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c + $quietZone;
                    $y = $r + $quietZone;
                    $rects[] = "<rect x=\"{$x}\" y=\"{$y}\" width=\"1\" height=\"1\" fill=\"#1e1b4b\"/>";
                }
            }
        }

        $rectsSvg = implode('', $rects);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$totalModules} {$totalModules}" width="{$targetSize}" height="{$targetSize}" shape-rendering="crispEdges">
    <rect width="{$totalModules}" height="{$totalModules}" fill="#ffffff" rx="1"/>
    {$rectsSvg}
</svg>
SVG;
    }

    /**
     * Render QR code for ANSI / Unicode console output using 2-row half blocks.
     */
    public static function toTerminal(string $text): string
    {
        $matrix = self::generateMatrix($text);
        $moduleCount = count($matrix);
        $quietZone = 2;
        $totalModules = $moduleCount + ($quietZone * 2);

        // Build expanded grid with quiet zone
        $grid = array_fill(0, $totalModules, array_fill(0, $totalModules, false));
        for ($r = 0; $r < $moduleCount; $r++) {
            for ($c = 0; $c < $moduleCount; $c++) {
                $grid[$r + $quietZone][$c + $quietZone] = $matrix[$r][$c];
            }
        }

        $lines = [];
        for ($r = 0; $r < $totalModules; $r += 2) {
            $line = '';
            for ($c = 0; $c < $totalModules; $c++) {
                $top = $grid[$r][$c];
                $bottom = ($r + 1 < $totalModules) ? $grid[$r + 1][$c] : false;

                // ANSI half blocks
                // top black, bottom black: ' ' (in inverted black-on-white terminal)
                // We use standard Unicode blocks:
                // █ = both dark, ▀ = top dark only, ▄ = bottom dark only, ' ' = both light
                if ($top && $bottom) {
                    $line .= '██';
                } elseif ($top && ! $bottom) {
                    $line .= '▀▀';
                } elseif (! $top && $bottom) {
                    $line .= '▄▄';
                } else {
                    $line .= '  ';
                }
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
