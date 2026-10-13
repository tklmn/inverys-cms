<?php

namespace App\Helpers;

/**
 * Self-contained QR Code encoder — no external dependencies.
 * Byte-mode, EC level M, versions 1–40. Outputs inline SVG.
 *
 * Algorithm follows ISO 18004 / the reference qrcode implementation:
 * patterns + format info are placed BEFORE data, mask is applied during
 * data placement, no separate reserved array needed.
 */
class QrEncoder
{
    // --- GF(256) tables (primitive polynomial 0x11d) ---
    private static array $exp;

    private static array $log;

    // Version config for EC level M: [totalCodewords, ecPerBlock, [[blockCount, dataPerBlock], ...]]
    private const V = [
        1 => [26,  10, [[1, 16]]],         2 => [44,  16, [[1, 28]]],
        3 => [70,  26, [[1, 44]]],          4 => [100, 18, [[2, 32]]],
        5 => [134, 24, [[2, 43]]],          6 => [172, 16, [[4, 27]]],
        7 => [196, 18, [[4, 31]]],          8 => [242, 22, [[2, 38], [2, 39]]],
        9 => [292, 22, [[3, 36], [2, 37]]], 10 => [346, 26, [[4, 43], [1, 44]]],
        11 => [404, 30, [[1, 50], [4, 51]]], 12 => [466, 22, [[6, 36], [2, 37]]],
        13 => [532, 22, [[8, 36], [1, 37]]], 14 => [581, 24, [[4, 36], [5, 37]]],
        15 => [655, 22, [[5, 36], [5, 37]]], 16 => [733, 24, [[7, 36], [3, 37]]],
        17 => [815, 28, [[10, 36], [1, 37]]], 18 => [901, 26, [[1, 36], [11, 37]]],
        19 => [991, 26, [[3, 45], [8, 46]]], 20 => [1085, 26, [[3, 45], [10, 46]]],
        21 => [1156, 28, [[7, 45], [4, 46]]], 22 => [1258, 28, [[4, 45], [9, 46]]],
        23 => [1364, 28, [[11, 45], [1, 46]]], 24 => [1474, 28, [[6, 45], [7, 46]]],
        25 => [1588, 28, [[8, 45], [5, 46]]], 26 => [1706, 28, [[10, 45], [3, 46]]],
        27 => [1828, 28, [[8, 45], [7, 46]]], 28 => [1921, 28, [[3, 45], [13, 46]]],
        29 => [2051, 28, [[7, 45], [10, 46]]], 30 => [2185, 28, [[5, 45], [13, 46]]],
        31 => [2323, 28, [[13, 45], [3, 46]]], 32 => [2465, 28, [[17, 45], [0, 46]]],
        33 => [2611, 28, [[17, 45], [1, 46]]], 34 => [2761, 28, [[13, 45], [6, 46]]],
        35 => [2876, 28, [[12, 45], [7, 46]]], 36 => [3034, 28, [[6, 45], [14, 46]]],
        37 => [3196, 28, [[17, 45], [4, 46]]], 38 => [3362, 28, [[4, 45], [18, 46]]],
        39 => [3532, 28, [[20, 45], [4, 46]]], 40 => [3706, 28, [[19, 45], [6, 46]]],
    ];

    // Alignment pattern center positions per version
    private const ALIGN = [
        1 => [],                            2 => [6, 18],
        3 => [6, 22],                        4 => [6, 26],
        5 => [6, 30],                        6 => [6, 34],
        7 => [6, 22, 38],                     8 => [6, 24, 42],
        9 => [6, 26, 46],                     10 => [6, 28, 50],
        11 => [6, 30, 54],                     12 => [6, 32, 58],
        13 => [6, 34, 62],                     14 => [6, 26, 46, 66],
        15 => [6, 26, 48, 70],                  16 => [6, 26, 50, 74],
        17 => [6, 30, 54, 78],                  18 => [6, 30, 56, 82],
        19 => [6, 30, 58, 86],                  20 => [6, 34, 62, 90],
        21 => [6, 28, 50, 72, 94],               22 => [6, 26, 50, 74, 98],
        23 => [6, 30, 54, 78, 102],              24 => [6, 28, 54, 80, 106],
        25 => [6, 32, 58, 84, 110],              26 => [6, 30, 58, 86, 114],
        27 => [6, 34, 62, 90, 118],              28 => [6, 26, 50, 74, 98, 122],
        29 => [6, 30, 54, 78, 102, 126],          30 => [6, 26, 52, 78, 104, 130],
        31 => [6, 30, 56, 82, 108, 134],          32 => [6, 34, 60, 86, 112, 138],
        33 => [6, 30, 58, 86, 114, 142],          34 => [6, 34, 62, 90, 118, 146],
        35 => [6, 30, 54, 78, 102, 126, 150],      36 => [6, 24, 50, 76, 102, 128, 154],
        37 => [6, 28, 54, 80, 106, 132, 158],      38 => [6, 32, 58, 84, 110, 136, 162],
        39 => [6, 26, 54, 82, 110, 138, 166],      40 => [6, 30, 58, 86, 114, 142, 170],
    ];

    // Mask functions indexed 0–7
    private const MASK_FNS = [
        0 => 'maskFn0', 1 => 'maskFn1', 2 => 'maskFn2', 3 => 'maskFn3',
        4 => 'maskFn4', 5 => 'maskFn5', 6 => 'maskFn6', 7 => 'maskFn7',
    ];

    private static function maskFn0(int $r, int $c): bool
    {
        return ($r + $c) % 2 === 0;
    }

    private static function maskFn1(int $r, int $c): bool
    {
        return $r % 2 === 0;
    }

    private static function maskFn2(int $r, int $c): bool
    {
        return $c % 3 === 0;
    }

    private static function maskFn3(int $r, int $c): bool
    {
        return ($r + $c) % 3 === 0;
    }

    private static function maskFn4(int $r, int $c): bool
    {
        return (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0;
    }

    private static function maskFn5(int $r, int $c): bool
    {
        return (($r * $c) % 2 + ($r * $c) % 3) === 0;
    }

    private static function maskFn6(int $r, int $c): bool
    {
        return (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0;
    }

    private static function maskFn7(int $r, int $c): bool
    {
        return (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0;
    }

    // =========================================================================
    //  Public API
    // =========================================================================

    public static function toSvg(string $text): string
    {
        self::initGf();
        $matrix = self::generate($text);
        $size = count($matrix);
        $quiet = 4;
        $total = $size + $quiet * 2;

        $d = '';
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c + $quiet;
                    $y = $r + $quiet;
                    $d .= "M{$x} {$y}h1v1h-1z";
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$total.' '.$total.'" style="width:200px;height:200px">'
            .'<rect width="100%" height="100%" fill="#fff"/>'
            .'<path d="'.$d.'" fill="#000"/>'
            .'</svg>';
    }

    // =========================================================================
    //  GF(256) + Reed-Solomon
    // =========================================================================

    private static function initGf(): void
    {
        if (isset(self::$exp)) {
            return;
        }
        self::$exp = array_fill(0, 256, 0);
        self::$log = array_fill(0, 256, 0);
        $v = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $v;
            self::$log[$v] = $i;
            $v <<= 1;
            if ($v >= 256) {
                $v ^= 0x11D;
            }
        }
        self::$exp[255] = self::$exp[0];
    }

    private static function gfMul(int $a, int $b): int
    {
        return ($a === 0 || $b === 0) ? 0 : self::$exp[(self::$log[$a] + self::$log[$b]) % 255];
    }

    private static function rsGenPoly(int $n): array
    {
        $p = [1];
        for ($i = 0; $i < $n; $i++) {
            $next = array_fill(0, count($p) + 1, 0);
            for ($j = 0, $len = count($p); $j < $len; $j++) {
                $next[$j] ^= $p[$j];
                $next[$j + 1] ^= self::gfMul($p[$j], self::$exp[$i]);
            }
            $p = $next;
        }

        return $p;
    }

    private static function rsEncode(array $data, int $ecCount): array
    {
        $gen = self::rsGenPoly($ecCount);
        $r = array_fill(0, $ecCount, 0);
        foreach ($data as $byte) {
            $coeff = $byte ^ $r[0];
            for ($j = 0; $j < $ecCount - 1; $j++) {
                $r[$j] = $r[$j + 1];
            }
            $r[$ecCount - 1] = 0;
            if ($coeff !== 0) {
                for ($j = 0; $j < $ecCount; $j++) {
                    $r[$j] ^= self::gfMul($gen[$j + 1], $coeff);
                }
            }
        }

        return $r;
    }

    // =========================================================================
    //  Data encoding (byte mode)
    // =========================================================================

    private static function chooseVersion(int $byteLen): int
    {
        foreach (self::V as $v => $cfg) {
            $dataCW = 0;
            foreach ($cfg[2] as [$cnt, $dc]) {
                $dataCW += $cnt * $dc;
            }
            $countBits = $v <= 9 ? 8 : 16;
            if (intdiv($dataCW * 8 - 4 - $countBits, 8) >= $byteLen) {
                return $v;
            }
        }

        throw new \RuntimeException('Data too long for QR code');
    }

    private static function encodeData(array $bytes, int $version): array
    {
        [, , $blocks] = self::V[$version];
        $dataCW = 0;
        foreach ($blocks as [$cnt, $dc]) {
            $dataCW += $cnt * $dc;
        }

        $bits = [];
        $push = function (int $val, int $len) use (&$bits) {
            for ($i = $len - 1; $i >= 0; $i--) {
                $bits[] = ($val >> $i) & 1;
            }
        };

        $push(0b0100, 4); // byte mode
        $push(count($bytes), $version <= 9 ? 8 : 16);
        foreach ($bytes as $b) {
            $push($b, 8);
        }

        $totalBits = $dataCW * 8;
        $push(0, min(4, $totalBits - count($bits)));
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        $pads = [0xEC, 0x11];
        $pi = 0;
        while (count($bits) < $totalBits) {
            $push($pads[$pi], 8);
            $pi ^= 1;
        }

        $out = [];
        for ($i = 0; $i < $dataCW; $i++) {
            $byte = 0;
            for ($b = 0; $b < 8; $b++) {
                $byte = ($byte << 1) | ($bits[$i * 8 + $b] ?? 0);
            }
            $out[] = $byte;
        }

        return $out;
    }

    private static function buildCodewords(array $dataBytes, int $version): array
    {
        [, $ecPer, $blocks] = self::V[$version];
        $dataBlocks = [];
        $ecBlocks = [];
        $off = 0;
        foreach ($blocks as [$cnt, $cw]) {
            for ($i = 0; $i < $cnt; $i++) {
                $block = array_slice($dataBytes, $off, $cw);
                $dataBlocks[] = $block;
                $ecBlocks[] = self::rsEncode($block, $ecPer);
                $off += $cw;
            }
        }

        $result = [];
        $maxData = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($dataBlocks as $b) {
                if ($i < count($b)) {
                    $result[] = $b[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPer; $i++) {
            foreach ($ecBlocks as $b) {
                $result[] = $b[$i];
            }
        }

        return $result;
    }

    // =========================================================================
    //  Matrix building — matches reference: patterns + format info placed first,
    //  then data with mask applied inline. No separate reserved array.
    // =========================================================================

    /**
     * Build the full QR matrix for the given text.
     * Returns a 2D array of 0/1 values (size × size).
     */
    private static function generate(string $text): array
    {
        self::initGf();
        $textBytes = array_values(unpack('C*', $text));
        $version = self::chooseVersion(count($textBytes));
        $dataBytes = self::encodeData($textBytes, $version);
        $codewords = self::buildCodewords($dataBytes, $version);
        $size = $version * 4 + 17;

        // Find best mask by testing all 8
        $bestMask = 0;
        $bestScore = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $matrix = self::buildMatrix($size, $version, $codewords, $m, true);
            $s = self::penalty($matrix, $size);
            if ($s < $bestScore) {
                $bestScore = $s;
                $bestMask = $m;
            }
        }

        return self::buildMatrix($size, $version, $codewords, $bestMask, false);
    }

    /**
     * Place all patterns, format/version info, and data into a matrix.
     * When $test=true, format info modules are set to false (for penalty eval).
     */
    private static function buildMatrix(int $size, int $version, array $codewords, int $mask, bool $test): array
    {
        $m = array_fill(0, $size, array_fill(0, $size, null));

        // 1. Finder patterns with separators
        self::placeFinder($m, $size, 0, 0);
        self::placeFinder($m, $size, $size - 7, 0);
        self::placeFinder($m, $size, 0, $size - 7);

        // 2. Alignment patterns
        $apos = self::ALIGN[$version];
        foreach ($apos as $ar) {
            foreach ($apos as $ac) {
                if ($ar <= 8 && $ac <= 8) {
                    continue;
                }
                if ($ar <= 8 && $ac >= $size - 8) {
                    continue;
                }
                if ($ar >= $size - 8 && $ac <= 8) {
                    continue;
                }
                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $m[$ar + $r][$ac + $c] = (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0)) ? 1 : 0;
                    }
                }
            }
        }

        // 3. Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            if ($m[6][$i] === null) {
                $m[6][$i] = ($i % 2 === 0) ? 1 : 0;
            }
            if ($m[$i][6] === null) {
                $m[$i][6] = ($i % 2 === 0) ? 1 : 0;
            }
        }

        // 4. Format info + dark module (placed BEFORE data — reserves positions)
        self::placeFormatInfo($m, $size, $mask, $test);

        // 6. Version info (V7+)
        if ($version >= 7) {
            self::placeVersionInfo($m, $size, $version, $test);
        }

        // 7. Data placement with mask applied inline
        self::placeData($m, $size, $codewords, $mask);

        return $m;
    }

    private static function placeFinder(array &$m, int $size, int $row, int $col): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $mr = $row + $r;
                $mc = $col + $c;
                if ($mr < 0 || $mr >= $size || $mc < 0 || $mc >= $size) {
                    continue;
                }
                if ($r === -1 || $r === 7 || $c === -1 || $c === 7) {
                    $val = 0;
                } elseif ($r === 0 || $r === 6 || $c === 0 || $c === 6) {
                    $val = 1;
                } elseif ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4) {
                    $val = 1;
                } else {
                    $val = 0;
                }
                $m[$mr][$mc] = $val;
            }
        }
    }

    // =========================================================================
    //  Format & version info — matches reference bit ordering exactly
    // =========================================================================

    private static function encodeFormatInfo(int $maskIdx): int
    {
        $data = (0b00 << 3) | $maskIdx; // EC level M = 00
        $bits = $data << 10;
        for ($i = 14; $i >= 10; $i--) {
            if ($bits & (1 << $i)) {
                $bits ^= 0b10100110111 << ($i - 10);
            }
        }

        return (($data << 10) | $bits) ^ 0b101010000010010;
    }

    /**
     * Place format info — matches reference library's exact loop structure.
     * Copy 1 ("vertical"): all 15 bits in column 8.
     * Copy 2 ("horizontal"): all 15 bits in row 8.
     */
    private static function placeFormatInfo(array &$m, int $size, int $mask, bool $test): void
    {
        $bits = self::encodeFormatInfo($mask);

        // Copy 1: column 8 (rows 0-5, 7-8, size-7 to size-1)
        for ($i = 0; $i < 15; $i++) {
            $mod = (! $test && (($bits >> $i) & 1) === 1) ? 1 : 0;

            if ($i < 6) {
                $m[$i][8] = $mod;
            } elseif ($i < 8) {
                $m[$i + 1][8] = $mod; // skip row 6 (timing)
            } else {
                $m[$size - 15 + $i][8] = $mod;
            }
        }

        // Fixed module (dark module)
        $m[$size - 8][8] = $test ? 0 : 1;

        // Copy 2: row 8 (cols size-1 to size-8, 7, 5-0)
        for ($i = 0; $i < 15; $i++) {
            $mod = (! $test && (($bits >> $i) & 1) === 1) ? 1 : 0;

            if ($i < 8) {
                $m[8][$size - $i - 1] = $mod;
            } elseif ($i < 9) {
                $m[8][15 - $i - 1 + 1] = $mod; // i=8 → col 7
            } else {
                $m[8][15 - $i - 1] = $mod; // i=9→col 5, ..., i=14→col 0
            }
        }
    }

    private static function placeVersionInfo(array &$m, int $size, int $version, bool $test): void
    {
        $data = $version << 12;
        $tmp = $data;
        for ($i = 17; $i >= 12; $i--) {
            if ($tmp & (1 << $i)) {
                $tmp ^= 0b1111100100101 << ($i - 12);
            }
        }
        $bits = $data | $tmp;

        for ($i = 0; $i < 18; $i++) {
            $mod = (! $test && (($bits >> $i) & 1) === 1) ? 1 : 0;
            $row = intdiv($i, 3);
            $col = $size - 11 + ($i % 3);
            $m[$row][$col] = $mod;
            $m[$col][$row] = $mod;
        }
    }

    // =========================================================================
    //  Data placement — zigzag with mask applied inline (reference algorithm)
    // =========================================================================

    private static function placeData(array &$m, int $size, array $codewords, int $mask): void
    {
        $fn = self::MASK_FNS[$mask];
        $bitIdx = 7;
        $byteIdx = 0;
        $dataLen = count($codewords);
        $inc = -1;
        $row = $size - 1;

        // Build column sequence like Python's range(size-1, 0, -2) with col-=1 for <=6
        $columns = [];
        for ($c = $size - 1; $c > 0; $c -= 2) {
            $columns[] = $c;
        }

        foreach ($columns as $col) {
            if ($col <= 6) {
                $col--;
            }

            while (true) {
                for ($dc = 0; $dc < 2; $dc++) {
                    $cc = $col - $dc;
                    if ($m[$row][$cc] === null) {
                        $dark = false;
                        if ($byteIdx < $dataLen) {
                            $dark = (($codewords[$byteIdx] >> $bitIdx) & 1) === 1;
                        }
                        if (self::$fn($row, $cc)) {
                            $dark = ! $dark;
                        }
                        $m[$row][$cc] = $dark ? 1 : 0;
                        $bitIdx--;
                        if ($bitIdx === -1) {
                            $byteIdx++;
                            $bitIdx = 7;
                        }
                    }
                }

                $row += $inc;
                if ($row < 0 || $row >= $size) {
                    $row -= $inc;
                    $inc = -$inc;
                    break;
                }
            }
        }
    }

    // =========================================================================
    //  Penalty score
    // =========================================================================

    private static function penalty(array $m, int $size): int
    {
        $score = 0;

        // Rule 1: runs of 5+ same-color
        for ($r = 0; $r < $size; $r++) {
            $cnt = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($m[$r][$c] === $m[$r][$c - 1]) {
                    $cnt++;
                    if ($cnt === 5) {
                        $score += 3;
                    } elseif ($cnt > 5) {
                        $score++;
                    }
                } else {
                    $cnt = 1;
                }
            }
        }
        for ($c = 0; $c < $size; $c++) {
            $cnt = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($m[$r][$c] === $m[$r - 1][$c]) {
                    $cnt++;
                    if ($cnt === 5) {
                        $score += 3;
                    } elseif ($cnt > 5) {
                        $score++;
                    }
                } else {
                    $cnt = 1;
                }
            }
        }

        // Rule 2: 2×2 blocks
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $m[$r][$c];
                if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) {
                    $score += 3;
                }
            }
        }

        // Rule 3: finder-like patterns
        $p1 = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $p2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c <= $size - 11; $c++) {
                $m1 = $m2 = true;
                for ($i = 0; $i < 11; $i++) {
                    if ($m[$r][$c + $i] !== $p1[$i]) {
                        $m1 = false;
                    }
                    if ($m[$r][$c + $i] !== $p2[$i]) {
                        $m2 = false;
                    }
                }
                if ($m1 || $m2) {
                    $score += 40;
                }
            }
        }
        for ($c = 0; $c < $size; $c++) {
            for ($r = 0; $r <= $size - 11; $r++) {
                $m1 = $m2 = true;
                for ($i = 0; $i < 11; $i++) {
                    if ($m[$r + $i][$c] !== $p1[$i]) {
                        $m1 = false;
                    }
                    if ($m[$r + $i][$c] !== $p2[$i]) {
                        $m2 = false;
                    }
                }
                if ($m1 || $m2) {
                    $score += 40;
                }
            }
        }

        // Rule 4: dark module ratio
        $dark = 0;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($m[$r][$c]) {
                    $dark++;
                }
            }
        }
        $pct = ($dark / ($size * $size)) * 100;
        $prev5 = intdiv((int) $pct, 5) * 5;
        $score += (int) (min(abs($prev5 - 50), abs($prev5 + 5 - 50)) / 5 * 10);

        return $score;
    }
}
