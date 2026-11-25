<?php

namespace Tests\Unit;

use App\Support\QrCodeEncoder;
use App\Support\SimulatedQr;
use PHPUnit\Framework\TestCase;

class QrCodeEncoderTest extends TestCase
{
    public function test_simulated_qr_payload_contains_demo_payment_details_without_real_bank_account(): void
    {
        $payload = SimulatedQr::payload('QR-ABCDEF123456', '2350000.00', 'demo10');

        $this->assertStringContainsString('SNEAKERSHOP DEMO PAYMENT', $payload);
        $this->assertStringContainsString('Ma tham chieu: QR-ABCDEF123456', $payload);
        $this->assertStringContainsString('So tien: 2.350.000 VND', $payload);
        $this->assertStringContainsString('Voucher: DEMO10', $payload);
        $this->assertStringContainsString('Khong dung TK ngan hang that', $payload);
        $this->assertStringNotContainsString('http://', $payload);
        $this->assertStringNotContainsString('https://', $payload);
    }

    public function test_qr_encoder_produces_valid_iso18004_structure_and_decodes_byte_payload_round_trip(): void
    {
        $samples = [
            'HELLO QR',
            SimulatedQr::payload('QR-ABCDEF123456', '2500000.00'),
            SimulatedQr::payload('QR-9876543210AB', '1350000.00', 'DEMO10'),
            str_repeat('SNEAKERSHOP-V10-TEST-', 10).'END',
        ];

        foreach ($samples as $expected) {
            $matrix = QrCodeEncoder::encode($expected);
            $size = count($matrix);
            $this->assertSame(1, ($size - 17) % 4 === 0 ? 1 : 0);
            $version = intdiv($size - 17, 4);
            $this->assertGreaterThanOrEqual(1, $version);
            $this->assertLessThanOrEqual(14, $version);

            // Check 3 finder patterns (7x7 outer ring + 3x3 center dark)
            foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$top, $left]) {
                for ($dr = 0; $dr < 7; $dr++) {
                    for ($dc = 0; $dc < 7; $dc++) {
                        $expectedDark = $dr === 0 || $dr === 6 || $dc === 0 || $dc === 6
                            || ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4);
                        $this->assertSame($expectedDark, $matrix[$top + $dr][$left + $dc]);
                    }
                }
            }

            // Check timing patterns
            for ($i = 8; $i < $size - 8; $i++) {
                $this->assertSame(($i % 2) === 0, $matrix[6][$i]);
                $this->assertSame(($i % 2) === 0, $matrix[$i][6]);
            }

            // Check dark module at (4*version + 9, 8)
            $this->assertTrue($matrix[4 * $version + 9][8]);

            // Read & verify format information (both copies must match & pass BCH(15,5) check)
            $copy1 = $this->readFormatBitsCopy1($matrix);
            $copy2 = $this->readFormatBitsCopy2($matrix, $size);
            $this->assertSame($copy1, $copy2);

            $unmaskedFormat = $copy1 ^ 0x5412;
            $this->assertSame(0, $this->bchRemainder($unmaskedFormat, 0x537, 14, 10));
            $ecLevelBits = ($unmaskedFormat >> 13) & 0b11;
            $mask = ($unmaskedFormat >> 10) & 0b111;
            $this->assertSame(0b00, $ecLevelBits); // Level M

            // Read & verify version information for Version >= 7
            if ($version >= 7) {
                $trVersion = 0;
                $blVersion = 0;
                for ($i = 0; $i < 18; $i++) {
                    $a = intdiv($i, 3);
                    $b = $size - 11 + ($i % 3);
                    if ($matrix[$a][$b]) {
                        $trVersion |= (1 << $i);
                    }
                    if ($matrix[$b][$a]) {
                        $blVersion |= (1 << $i);
                    }
                }
                $this->assertSame($trVersion, $blVersion);
                $this->assertSame($version, $trVersion >> 12);
                $this->assertSame(0, $this->bchRemainder($trVersion, 0x1F25, 17, 12));
            }

            // Unmask and decode byte-mode data stream directly from the matrix
            $decoded = $this->decodeBytePayloadFromMatrix($matrix, $version, $mask);
            $this->assertSame($expected, $decoded);
        }
    }

    public function test_simulated_qr_svg_renders_with_four_module_quiet_zone_and_crisp_black_on_white(): void
    {
        $matrix = SimulatedQr::matrix('QR-ABCDEF123456', '1500000.00', 'DEMO50K');
        $expectedViewBox = count($matrix) + 8;

        $svg1 = SimulatedQr::svg('QR-ABCDEF123456', '1500000.00', 'DEMO50K');
        $svg2 = SimulatedQr::svg('QR-ABCDEF123456', '1500000.00', 'DEMO50K');

        $this->assertSame($svg1, $svg2);
        $this->assertStringContainsString('viewBox="0 0 '.$expectedViewBox.' '.$expectedViewBox.'"', $svg1);
        $this->assertStringContainsString('class="qr-demo-svg"', $svg1);
        $this->assertStringContainsString('shape-rendering="crispEdges"', $svg1);
        $this->assertStringContainsString('fill="#ffffff"', $svg1);
        $this->assertStringContainsString('fill="#000000"', $svg1);
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     */
    private function readFormatBitsCopy1(array $matrix): int
    {
        $coords = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        $bits = 0;
        foreach ($coords as [$r, $c]) {
            $bits = ($bits << 1) | ($matrix[$r][$c] ? 1 : 0);
        }

        return $bits;
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     */
    private function readFormatBitsCopy2(array $matrix, int $size): int
    {
        $bits = 0;
        for ($i = 0; $i < 7; $i++) {
            $bits = ($bits << 1) | ($matrix[$size - 1 - $i][8] ? 1 : 0);
        }
        for ($i = 0; $i < 8; $i++) {
            $bits = ($bits << 1) | ($matrix[8][$size - 8 + $i] ? 1 : 0);
        }

        return $bits;
    }

    private function bchRemainder(int $value, int $generator, int $topBit, int $degree): int
    {
        $rem = $value;
        for ($i = $topBit; $i >= $degree; $i--) {
            if (($rem >> $i) & 1) {
                $rem ^= ($generator << ($i - $degree));
            }
        }

        return $rem;
    }

    /**
     * Independent verifier: reads raw unmasked codewords, de-interleaves Level M blocks, and extracts byte-mode text.
     *
     * @param  array<int, array<int, bool>>  $matrix
     */
    private function decodeBytePayloadFromMatrix(array $matrix, int $version, int $mask): string
    {
        $size = count($matrix);
        $isFunc = array_fill(0, $size, array_fill(0, $size, false));

        // Mark finder + separator + format areas
        for ($r = 0; $r < 9; $r++) {
            for ($c = 0; $c < 9; $c++) {
                $isFunc[$r][$c] = true;
            }
            for ($c = $size - 8; $c < $size; $c++) {
                $isFunc[$r][$c] = true;
            }
        }
        for ($r = $size - 8; $r < $size; $r++) {
            for ($c = 0; $c < 9; $c++) {
                $isFunc[$r][$c] = true;
            }
        }
        for ($i = 0; $i < $size; $i++) {
            $isFunc[6][$i] = true;
            $isFunc[$i][6] = true;
        }
        $alignTable = [
            1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
            7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
            11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62], 14 => [6, 26, 46, 66],
        ];
        foreach ($alignTable[$version] as $rc) {
            foreach ($alignTable[$version] as $cc) {
                if (($rc < 9 && $cc < 9) || ($rc < 9 && $cc >= $size - 8) || ($rc >= $size - 8 && $cc < 9)) {
                    continue;
                }
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $isFunc[$rc + $dr][$cc + $dc] = true;
                    }
                }
            }
        }
        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $isFunc[$i][$size - 11 + $j] = true;
                    $isFunc[$size - 11 + $j][$i] = true;
                }
            }
        }

        // Read unmasked bitstream in standard zigzag order
        $bits = [];
        $upward = true;
        for ($col = $size - 1; $col >= 1; $col -= 2) {
            if ($col === 6) {
                $col = 5;
            }
            for ($step = 0; $step < $size; $step++) {
                $r = $upward ? ($size - 1 - $step) : $step;
                for ($dc = 0; $dc < 2; $dc++) {
                    $c = $col - $dc;
                    if ($isFunc[$r][$c]) {
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
                    };
                    $bit = $matrix[$r][$c] !== $invert;
                    $bits[] = $bit ? 1 : 0;
                }
            }
            $upward = ! $upward;
        }

        $codewords = [];
        for ($i = 0; $i + 8 <= count($bits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $codewords[] = $byte;
        }

        // De-interleave data codewords according to ISO/IEC 18004 Table 9 (Level M)
        $specs = [
            1 => [1, 16, 0, 0], 2 => [1, 28, 0, 0], 3 => [1, 44, 0, 0], 4 => [2, 32, 0, 0],
            5 => [2, 43, 0, 0], 6 => [4, 27, 0, 0], 7 => [4, 31, 0, 0], 8 => [2, 38, 2, 39],
            9 => [3, 36, 2, 37], 10 => [4, 43, 1, 44], 11 => [1, 50, 4, 51], 12 => [6, 36, 2, 37],
            13 => [8, 37, 1, 38], 14 => [4, 40, 5, 41],
        ];
        [$g1Blocks, $g1Len, $g2Blocks, $g2Len] = $specs[$version];
        $totalBlocks = $g1Blocks + $g2Blocks;
        $blocks = array_fill(0, $totalBlocks, []);
        $idx = 0;
        $maxLen = max($g1Len, $g2Len);
        for ($pos = 0; $pos < $maxLen; $pos++) {
            for ($b = 0; $b < $totalBlocks; $b++) {
                $bLen = $b < $g1Blocks ? $g1Len : $g2Len;
                if ($pos < $bLen) {
                    $blocks[$b][] = $codewords[$idx++];
                }
            }
        }

        $dataBits = [];
        foreach ($blocks as $block) {
            foreach ($block as $byte) {
                for ($b = 7; $b >= 0; $b--) {
                    $dataBits[] = ($byte >> $b) & 1;
                }
            }
        }

        $cursor = 0;
        $readInt = function (int $len) use ($dataBits, &$cursor): int {
            $val = 0;
            for ($i = 0; $i < $len; $i++) {
                $val = ($val << 1) | $dataBits[$cursor++];
            }

            return $val;
        };

        $mode = $readInt(4);
        $this->assertSame(0b0100, $mode);
        $byteLen = $readInt($version < 10 ? 8 : 16);
        $out = '';
        for ($i = 0; $i < $byteLen; $i++) {
            $out .= chr($readInt(8));
        }

        return $out;
    }
}
