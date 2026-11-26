<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Pure-PHP ISO/IEC 18004 QR Code encoder (Byte mode, Error Correction Level M, Versions 1-14).
 * Generates standards-compliant scannable QR matrices with zero external dependencies.
 */
final class QrCodeEncoder
{
    private const EC_LEVEL_M_BITS = 0b00;

    private const FORMAT_MASK = 0x5412;

    /**
     * ISO/IEC 18004 Table 9 for Error Correction Level M (Versions 1..14):
     * version => [totalCodewords, ecPerBlock, group1Blocks, group1DataCodewords, group2Blocks, group2DataCodewords]
     *
     * @var array<int, array{0:int, 1:int, 2:int, 3:int, 4:int, 5:int}>
     */
    private const VERSION_SPEC_M = [
        1 => [26, 10, 1, 16, 0, 0],
        2 => [44, 16, 1, 28, 0, 0],
        3 => [70, 26, 1, 44, 0, 0],
        4 => [100, 18, 2, 32, 0, 0],
        5 => [134, 24, 2, 43, 0, 0],
        6 => [172, 16, 4, 27, 0, 0],
        7 => [196, 18, 4, 31, 0, 0],
        8 => [242, 22, 2, 38, 2, 39],
        9 => [292, 22, 3, 36, 2, 37],
        10 => [346, 26, 4, 43, 1, 44],
        11 => [404, 30, 1, 50, 4, 51],
        12 => [466, 22, 6, 36, 2, 37],
        13 => [532, 22, 8, 37, 1, 38],
        14 => [581, 24, 4, 40, 5, 41],
    ];

    /**
     * ISO/IEC 18004 Table E.1 alignment pattern center coordinates (Versions 1..14).
     *
     * @var array<int, list<int>>
     */
    private const ALIGNMENT_CENTERS = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
        11 => [6, 30, 54],
        12 => [6, 32, 58],
        13 => [6, 34, 62],
        14 => [6, 26, 46, 66],
    ];

    /**
     * ISO/IEC 18004 Table 1 remainder bits (Versions 1..14).
     *
     * @var array<int, int>
     */
    private const REMAINDER_BITS = [
        1 => 0,
        2 => 7,
        3 => 7,
        4 => 7,
        5 => 7,
        6 => 7,
        7 => 0,
        8 => 0,
        9 => 0,
        10 => 0,
        11 => 0,
        12 => 0,
        13 => 0,
        14 => 3,
    ];

    /** @var array<int, int>|null */
    private static ?array $gfExp = null;

    /** @var array<int, int>|null */
    private static ?array $gfLog = null;

    /**
     * Encode arbitrary byte payload into a square boolean QR module matrix (true = dark, false = light).
     *
     * @return array<int, array<int, bool>>
     */
    public static function encode(string $payload): array
    {
        $byteLength = strlen($payload);
        $version = self::selectVersion($byteLength);
        $spec = self::VERSION_SPEC_M[$version];
        $size = 17 + $version * 4;

        $dataCodewords = self::encodeDataCodewords($payload, $version, $spec);
        $bitStream = self::buildFinalBitStream($dataCodewords, $version, $spec);

        [$baseMatrix, $isFunction] = self::buildFunctionMatrix($version, $size);
        self::placeDataBits($baseMatrix, $isFunction, $bitStream, $size);

        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestMatrix = $baseMatrix;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::applyMask($baseMatrix, $isFunction, $mask, $size);
            self::writeFormatInfo($candidate, $mask, $size);
            if ($version >= 7) {
                self::writeVersionInfo($candidate, $version, $size);
            }

            $penalty = self::calculatePenalty($candidate, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestMatrix = $candidate;
            }
        }

        // Ensure deterministic format info for selected mask.
        self::writeFormatInfo($bestMatrix, $bestMask, $size);
        if ($version >= 7) {
            self::writeVersionInfo($bestMatrix, $version, $size);
        }

        return $bestMatrix;
    }

    private static function selectVersion(int $byteLength): int
    {
        foreach (self::VERSION_SPEC_M as $version => $spec) {
            $totalDataCodewords = $spec[2] * $spec[3] + $spec[4] * $spec[5];
            $countBits = $version < 10 ? 8 : 16;
            $maxBytes = intdiv($totalDataCodewords * 8 - 4 - $countBits, 8);
            if ($byteLength <= $maxBytes) {
                return $version;
            }
        }

        throw new InvalidArgumentException('QR payload exceeds supported Version 14 Level M capacity.');
    }

    /**
     * @param  array{0:int, 1:int, 2:int, 3:int, 4:int, 5:int}  $spec
     * @return list<int>
     */
    private static function encodeDataCodewords(string $payload, int $version, array $spec): array
    {
        $totalDataCodewords = $spec[2] * $spec[3] + $spec[4] * $spec[5];
        $capacityBits = $totalDataCodewords * 8;
        $countBits = $version < 10 ? 8 : 16;

        $bits = [];
        // Byte mode indicator: 0100
        self::appendBits($bits, 0b0100, 4);
        // Character count indicator
        self::appendBits($bits, strlen($payload), $countBits);

        $len = strlen($payload);
        for ($i = 0; $i < $len; $i++) {
            self::appendBits($bits, ord($payload[$i]), 8);
        }

        // Terminator (up to 4 zero bits)
        $terminatorLen = min(4, $capacityBits - count($bits));
        self::appendBits($bits, 0, $terminatorLen);

        // Pad to byte boundary
        while ((count($bits) % 8) !== 0) {
            $bits[] = 0;
        }

        $codewords = [];
        $bitCount = count($bits);
        for ($i = 0; $i < $bitCount; $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $codewords[] = $byte;
        }

        // Pad alternating 0xEC and 0x11 codewords
        $padBytes = [0xEC, 0x11];
        $padIndex = 0;
        while (count($codewords) < $totalDataCodewords) {
            $codewords[] = $padBytes[$padIndex % 2];
            $padIndex++;
        }

        return $codewords;
    }

    /**
     * @param  list<int>  $dataCodewords
     * @param  array{0:int, 1:int, 2:int, 3:int, 4:int, 5:int}  $spec
     * @return list<int>
     */
    private static function buildFinalBitStream(array $dataCodewords, int $version, array $spec): array
    {
        [, $ecPerBlock, $g1Blocks, $g1DataLen, $g2Blocks, $g2DataLen] = $spec;

        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;

        for ($b = 0; $b < $g1Blocks; $b++) {
            $block = array_slice($dataCodewords, $offset, $g1DataLen);
            $offset += $g1DataLen;
            $dataBlocks[] = $block;
            $ecBlocks[] = self::reedSolomonRemainder($block, $ecPerBlock);
        }

        for ($b = 0; $b < $g2Blocks; $b++) {
            $block = array_slice($dataCodewords, $offset, $g2DataLen);
            $offset += $g2DataLen;
            $dataBlocks[] = $block;
            $ecBlocks[] = self::reedSolomonRemainder($block, $ecPerBlock);
        }

        $interleaved = [];
        $maxDataLen = max($g1DataLen, $g2DataLen);
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $interleaved[] = $block[$i];
                }
            }
        }

        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $ecBlock) {
                $interleaved[] = $ecBlock[$i];
            }
        }

        $bits = [];
        foreach ($interleaved as $byte) {
            self::appendBits($bits, $byte, 8);
        }

        $remainder = self::REMAINDER_BITS[$version];
        self::appendBits($bits, 0, $remainder);

        return $bits;
    }

    /**
     * @return array{0: array<int, array<int, bool>>, 1: array<int, array<int, bool>>}
     */
    private static function buildFunctionMatrix(int $version, int $size): array
    {
        $matrix = array_fill(0, $size, array_fill(0, $size, false));
        $isFunction = array_fill(0, $size, array_fill(0, $size, false));

        // 3 Finder patterns + separators
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$top, $left]) {
            for ($dr = -1; $dr <= 7; $dr++) {
                for ($dc = -1; $dc <= 7; $dc++) {
                    $r = $top + $dr;
                    $c = $left + $dc;
                    if ($r < 0 || $r >= $size || $c < 0 || $c >= $size) {
                        continue;
                    }

                    $isDark = ($dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6)
                        && (
                            $dr === 0 || $dr === 6 || $dc === 0 || $dc === 6
                            || ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4)
                        );
                    $matrix[$r][$c] = $isDark;
                    $isFunction[$r][$c] = true;
                }
            }
        }

        // Alignment patterns
        $centers = self::ALIGNMENT_CENTERS[$version];
        foreach ($centers as $rCenter) {
            foreach ($centers as $cCenter) {
                if (($rCenter < 9 && $cCenter < 9)
                    || ($rCenter < 9 && $cCenter >= $size - 8)
                    || ($rCenter >= $size - 8 && $cCenter < 9)) {
                    continue;
                }

                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $dist = max(abs($dr), abs($dc));
                        $matrix[$rCenter + $dr][$cCenter + $dc] = ($dist !== 1);
                        $isFunction[$rCenter + $dr][$cCenter + $dc] = true;
                    }
                }
            }
        }

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $isDark = ($i % 2) === 0;
            if (! $isFunction[6][$i]) {
                $matrix[6][$i] = $isDark;
                $isFunction[6][$i] = true;
            }
            if (! $isFunction[$i][6]) {
                $matrix[$i][6] = $isDark;
                $isFunction[$i][6] = true;
            }
        }

        // Reserve format information areas
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $isFunction[8][$i] = true;
                $isFunction[$i][8] = true;
            }
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $isFunction[8][$i] = true;
            $isFunction[$i][8] = true;
        }

        // Dark module (always dark at row = 4 * version + 9, col = 8)
        $matrix[4 * $version + 9][8] = true;
        $isFunction[4 * $version + 9][8] = true;

        // Reserve version information areas for Version >= 7
        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $isFunction[$i][$size - 11 + $j] = true;
                    $isFunction[$size - 11 + $j][$i] = true;
                }
            }
        }

        return [$matrix, $isFunction];
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     * @param  array<int, array<int, bool>>  $isFunction
     * @param  list<int>  $bits
     */
    private static function placeDataBits(array &$matrix, array $isFunction, array $bits, int $size): void
    {
        $bitIndex = 0;
        $bitCount = count($bits);
        $upward = true;

        for ($col = $size - 1; $col >= 1; $col -= 2) {
            if ($col === 6) {
                $col = 5;
            }

            for ($step = 0; $step < $size; $step++) {
                $row = $upward ? ($size - 1 - $step) : $step;
                for ($dc = 0; $dc < 2; $dc++) {
                    $c = $col - $dc;
                    if ($isFunction[$row][$c]) {
                        continue;
                    }

                    $matrix[$row][$c] = $bitIndex < $bitCount && $bits[$bitIndex] === 1;
                    $bitIndex++;
                }
            }

            $upward = ! $upward;
        }
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     * @param  array<int, array<int, bool>>  $isFunction
     * @return array<int, array<int, bool>>
     */
    private static function applyMask(array $matrix, array $isFunction, int $mask, int $size): array
    {
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($isFunction[$r][$c]) {
                    continue;
                }

                $invert = match ($mask) {
                    0 => (($r + $c) % 2) === 0,
                    1 => ($r % 2) === 0,
                    2 => ($c % 3) === 0,
                    3 => (($r + $c) % 3) === 0,
                    4 => ((intdiv($r, 2) + intdiv($c, 3)) % 2) === 0,
                    5 => ((($r * $c) % 2) + (($r * $c) % 3)) === 0,
                    6 => (((($r * $c) % 2) + (($r * $c) % 3)) % 2) === 0,
                    7 => (((($r + $c) % 2) + (($r * $c) % 3)) % 2) === 0,
                    default => false,
                };

                if ($invert) {
                    $matrix[$r][$c] = ! $matrix[$r][$c];
                }
            }
        }

        return $matrix;
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     */
    private static function writeFormatInfo(array &$matrix, int $mask, int $size): void
    {
        $data = (self::EC_LEVEL_M_BITS << 3) | $mask;
        $rem = $data << 10;
        for ($i = 14; $i >= 10; $i--) {
            if (($rem >> $i) & 1) {
                $rem ^= (0x537 << ($i - 10));
            }
        }
        $bits = (($data << 10) | $rem) ^ self::FORMAT_MASK;

        // Copy 1 around top-left finder: MSB (bit 14) -> LSB (bit 0)
        $topLeftCoords = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        for ($i = 0; $i < 15; $i++) {
            [$r, $c] = $topLeftCoords[$i];
            $matrix[$r][$c] = ((($bits >> (14 - $i)) & 1) === 1);
        }

        // Copy 2 along bottom-left (bits 14..8) and top-right (bits 7..0)
        for ($i = 0; $i < 7; $i++) {
            $matrix[$size - 1 - $i][8] = ((($bits >> (14 - $i)) & 1) === 1);
        }
        for ($i = 0; $i < 8; $i++) {
            $matrix[8][$size - 8 + $i] = ((($bits >> (7 - $i)) & 1) === 1);
        }
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     */
    private static function writeVersionInfo(array &$matrix, int $version, int $size): void
    {
        $rem = $version << 12;
        for ($i = 17; $i >= 12; $i--) {
            if (($rem >> $i) & 1) {
                $rem ^= (0x1F25 << ($i - 12));
            }
        }
        $bits = ($version << 12) | $rem;

        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $a = intdiv($i, 3);
            $b = $size - 11 + ($i % 3);
            $matrix[$a][$b] = $dark;
            $matrix[$b][$a] = $dark;
        }
    }

    /**
     * ISO/IEC 18004 Section 7.8.3.1 mask evaluation penalty score.
     *
     * @param  array<int, array<int, bool>>  $matrix
     */
    private static function calculatePenalty(array $matrix, int $size): int
    {
        $penalty = 0;

        // N1: Adjacent modules in row/column in same color (5+ => 3 + (len - 5))
        for ($r = 0; $r < $size; $r++) {
            $runColor = $matrix[$r][0];
            $runLen = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($matrix[$r][$c] === $runColor) {
                    $runLen++;
                } else {
                    if ($runLen >= 5) {
                        $penalty += 3 + ($runLen - 5);
                    }
                    $runColor = $matrix[$r][$c];
                    $runLen = 1;
                }
            }
            if ($runLen >= 5) {
                $penalty += 3 + ($runLen - 5);
            }
        }

        for ($c = 0; $c < $size; $c++) {
            $runColor = $matrix[0][$c];
            $runLen = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($matrix[$r][$c] === $runColor) {
                    $runLen++;
                } else {
                    if ($runLen >= 5) {
                        $penalty += 3 + ($runLen - 5);
                    }
                    $runColor = $matrix[$r][$c];
                    $runLen = 1;
                }
            }
            if ($runLen >= 5) {
                $penalty += 3 + ($runLen - 5);
            }
        }

        // N2: 2x2 blocks of same color
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $color = $matrix[$r][$c];
                if ($matrix[$r][$c + 1] === $color && $matrix[$r + 1][$c] === $color && $matrix[$r + 1][$c + 1] === $color) {
                    $penalty += 3;
                }
            }
        }

        // N3: Finder-like 1:1:3:1:1 patterns with 4 light modules on either side
        $patternA = [true, false, true, true, true, false, true, false, false, false, false];
        $patternB = [false, false, false, false, true, false, true, true, true, false, true];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c <= $size - 11; $c++) {
                $matchA = true;
                $matchB = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($matrix[$r][$c + $k] !== $patternA[$k]) {
                        $matchA = false;
                    }
                    if ($matrix[$r][$c + $k] !== $patternB[$k]) {
                        $matchB = false;
                    }
                    if (! $matchA && ! $matchB) {
                        break;
                    }
                }
                if ($matchA || $matchB) {
                    $penalty += 40;
                }
            }
        }
        for ($c = 0; $c < $size; $c++) {
            for ($r = 0; $r <= $size - 11; $r++) {
                $matchA = true;
                $matchB = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($matrix[$r + $k][$c] !== $patternA[$k]) {
                        $matchA = false;
                    }
                    if ($matrix[$r + $k][$c] !== $patternB[$k]) {
                        $matchB = false;
                    }
                    if (! $matchA && ! $matchB) {
                        break;
                    }
                }
                if ($matchA || $matchB) {
                    $penalty += 40;
                }
            }
        }

        // N4: Balance of dark and light modules
        $darkCount = 0;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c]) {
                    $darkCount++;
                }
            }
        }
        $total = $size * $size;
        $k = intdiv(abs($darkCount * 20 - $total * 10) + $total - 1, $total) - 1;
        if ($k > 0) {
            $penalty += $k * 10;
        }

        return $penalty;
    }

    /**
     * @param  list<int>  $data
     * @return list<int>
     */
    private static function reedSolomonRemainder(array $data, int $degree): array
    {
        $generator = self::reedSolomonGenerator($degree);
        $remainder = array_fill(0, $degree, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($remainder);
            $remainder[] = 0;
            if ($factor === 0) {
                continue;
            }
            for ($i = 0; $i < $degree; $i++) {
                $remainder[$i] ^= self::gfMultiply($generator[$i], $factor);
            }
        }

        return $remainder;
    }

    /**
     * Returns coefficients of (x - a^0)(x - a^1)...(x - a^(degree-1)) excluding leading x^degree term.
     *
     * @return list<int>
     */
    private static function reedSolomonGenerator(int $degree): array
    {
        self::initGaloisField();
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            $root = self::$gfExp[$i];
            foreach ($poly as $idx => $coeff) {
                $next[$idx] ^= $coeff;
                $next[$idx + 1] ^= self::gfMultiply($coeff, $root);
            }
            $poly = $next;
        }

        array_shift($poly);

        return $poly;
    }

    private static function initGaloisField(): void
    {
        if (self::$gfExp !== null) {
            return;
        }

        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        self::$gfExp = $exp;
        self::$gfLog = $log;
    }

    private static function gfMultiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        self::initGaloisField();

        return self::$gfExp[self::$gfLog[$a] + self::$gfLog[$b]];
    }

    /**
     * @param  list<int>  $bits
     */
    private static function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }
}
