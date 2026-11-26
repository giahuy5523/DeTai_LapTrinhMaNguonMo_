<?php

namespace App\Support;

final class SimulatedQr
{
    private const QUIET_ZONE_MODULES = 4;

    public static function reference(?string $token, ?int $orderId = null): string
    {
        if (is_string($token) && preg_match('/^[a-f0-9]{64}$/iD', $token)) {
            return 'QR-'.strtoupper(substr($token, 0, 12));
        }

        return $orderId !== null ? 'QR-ORD-'.$orderId : 'QR-DEMO';
    }

    public static function payload(string $reference, string $amountDecimal, ?string $voucherCode = null): string
    {
        $lines = [
            'SNEAKERSHOP DEMO PAYMENT',
            'Ma tham chieu: '.$reference,
            'So tien: '.Money::format($amountDecimal).' VND',
        ];

        if (is_string($voucherCode) && trim($voucherCode) !== '') {
            $lines[] = 'Voucher: '.mb_strtoupper(trim($voucherCode));
        }

        $lines[] = 'Noi dung: Thanh toan mo phong - Khong dung TK ngan hang that';

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array<int, bool>>
     */
    public static function matrix(string $reference, string $amountDecimal, ?string $voucherCode = null): array
    {
        return QrCodeEncoder::encode(self::payload($reference, $amountDecimal, $voucherCode));
    }

    public static function svg(string $reference, string $amountDecimal, ?string $voucherCode = null): string
    {
        $matrix = self::matrix($reference, $amountDecimal, $voucherCode);
        $moduleCount = count($matrix);
        $quiet = self::QUIET_ZONE_MODULES;
        $viewBoxSize = $moduleCount + $quiet * 2;
        $ariaLabel = htmlspecialchars(
            'Mã QR thanh toán demo tham chiếu '.$reference.' số tiền '.Money::format($amountDecimal).' đ',
            ENT_QUOTES,
            'UTF-8'
        );

        $pathParts = [];
        for ($row = 0; $row < $moduleCount; $row++) {
            $col = 0;
            while ($col < $moduleCount) {
                if (! $matrix[$row][$col]) {
                    $col++;

                    continue;
                }

                $runStart = $col;
                while ($col < $moduleCount && $matrix[$row][$col]) {
                    $col++;
                }
                $runLen = $col - $runStart;
                $x = $quiet + $runStart;
                $y = $quiet + $row;
                $pathParts[] = "M{$x} {$y}h{$runLen}v1h-{$runLen}z";
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$viewBoxSize.' '.$viewBoxSize.'" '
            .'width="220" height="220" class="qr-demo-svg" shape-rendering="crispEdges" role="img" aria-label="'.$ariaLabel.'">'
            .'<rect width="'.$viewBoxSize.'" height="'.$viewBoxSize.'" fill="#ffffff"/>'
            .'<path d="'.implode('', $pathParts).'" fill="#000000"/>'
            .'</svg>';
    }
}
