<?php

namespace App\Support;

final class SimulatedQr
{
    private const GRID_SIZE = 25;

    private const MODULE_PX = 8;

    private const QUIET_ZONE_PX = 16;

    public static function reference(?string $token, ?int $orderId = null): string
    {
        if (is_string($token) && preg_match('/^[a-f0-9]{64}$/iD', $token)) {
            return 'QR-'.strtoupper(substr($token, 0, 12));
        }

        return $orderId !== null ? 'QR-ORD-'.$orderId : 'QR-DEMO';
    }

    public static function payload(string $reference, string $amountDecimal): string
    {
        return 'SNEAKERSHOP-SIMULATED-QR|REF:'.$reference.'|VND:'.$amountDecimal.'|DEMO-NO-REAL-ACCOUNT';
    }

    public static function svg(string $reference, string $amountDecimal): string
    {
        $matrix = self::buildMatrix(self::payload($reference, $amountDecimal));
        $viewBoxSize = self::QUIET_ZONE_PX * 2 + self::GRID_SIZE * self::MODULE_PX;
        $ariaLabel = htmlspecialchars(
            'Mã QR mô phỏng tham chiếu '.$reference.' số tiền '.Money::format($amountDecimal).' đ',
            ENT_QUOTES,
            'UTF-8'
        );

        $rects = [];
        for ($row = 0; $row < self::GRID_SIZE; $row++) {
            for ($col = 0; $col < self::GRID_SIZE; $col++) {
                if (! $matrix[$row][$col]) {
                    continue;
                }

                $x = self::QUIET_ZONE_PX + $col * self::MODULE_PX;
                $y = self::QUIET_ZONE_PX + $row * self::MODULE_PX;
                $rects[] = '<rect x="'.$x.'" y="'.$y.'" width="'.self::MODULE_PX.'" height="'.self::MODULE_PX.'" fill="#12372a"/>';
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$viewBoxSize.' '.$viewBoxSize.'" '
            .'width="200" height="200" class="qr-demo-svg" role="img" aria-label="'.$ariaLabel.'">'
            .'<rect width="'.$viewBoxSize.'" height="'.$viewBoxSize.'" rx="12" fill="#ffffff"/>'
            .implode('', $rects)
            .'</svg>';
    }

    /**
     * Build a deterministic 25x25 QR-style matrix without external network calls.
     *
     * @return array<int, array<int, bool>>
     */
    private static function buildMatrix(string $payload): array
    {
        $matrix = array_fill(0, self::GRID_SIZE, array_fill(0, self::GRID_SIZE, null));

        foreach ([[0, 0], [0, self::GRID_SIZE - 7], [self::GRID_SIZE - 7, 0]] as [$topRow, $leftCol]) {
            self::placeFinderWithSeparator($matrix, $topRow, $leftCol);
        }

        self::placeAlignmentPattern($matrix, 16, 16);
        self::placeTimingPatterns($matrix);

        // Fill remaining modules deterministically from SHA-256 digests of the payload.
        $bitIndex = 0;
        $digestCache = [];
        for ($row = 0; $row < self::GRID_SIZE; $row++) {
            for ($col = 0; $col < self::GRID_SIZE; $col++) {
                if ($matrix[$row][$col] !== null) {
                    continue;
                }

                $chunk = intdiv($bitIndex, 256);
                if (! isset($digestCache[$chunk])) {
                    $digestCache[$chunk] = hash('sha256', $payload.'|'.$chunk, true);
                }

                $localBit = $bitIndex % 256;
                $byte = ord($digestCache[$chunk][intdiv($localBit, 8)]);
                $matrix[$row][$col] = (($byte >> (7 - ($localBit % 8))) & 1) === 1;
                $bitIndex++;
            }
        }

        /** @var array<int, array<int, bool>> $matrix */
        return $matrix;
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     */
    private static function placeFinderWithSeparator(array &$matrix, int $topRow, int $leftCol): void
    {
        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                $r = $topRow + $dr;
                $c = $leftCol + $dc;
                if ($r < 0 || $r >= self::GRID_SIZE || $c < 0 || $c >= self::GRID_SIZE) {
                    continue;
                }

                if ($dr === -1 || $dr === 7 || $dc === -1 || $dc === 7) {
                    $matrix[$r][$c] = false;
                } elseif ($dr === 0 || $dr === 6 || $dc === 0 || $dc === 6) {
                    $matrix[$r][$c] = true;
                } elseif ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4) {
                    $matrix[$r][$c] = true;
                } else {
                    $matrix[$r][$c] = false;
                }
            }
        }
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     */
    private static function placeAlignmentPattern(array &$matrix, int $topRow, int $leftCol): void
    {
        for ($dr = 0; $dr < 5; $dr++) {
            for ($dc = 0; $dc < 5; $dc++) {
                $isDark = $dr === 0 || $dr === 4 || $dc === 0 || $dc === 4 || ($dr === 2 && $dc === 2);
                $matrix[$topRow + $dr][$leftCol + $dc] = $isDark;
            }
        }
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     */
    private static function placeTimingPatterns(array &$matrix): void
    {
        for ($i = 8; $i <= self::GRID_SIZE - 9; $i++) {
            $isDark = ($i % 2) === 0;
            if ($matrix[6][$i] === null) {
                $matrix[6][$i] = $isDark;
            }
            if ($matrix[$i][6] === null) {
                $matrix[$i][6] = $isDark;
            }
        }
    }
}
