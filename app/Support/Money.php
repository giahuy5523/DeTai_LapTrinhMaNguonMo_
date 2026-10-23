<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class Money
{
    public const MAX_CENTS = 999999999999999;

    public static function cents(string|int|float $value): int
    {
        if (! preg_match('/^(\d{1,13})(?:\.(\d{1,2}))?$/D', (string) $value, $parts)) {
            throw ValidationException::withMessages(['cart' => 'Giá sản phẩm không hợp lệ.']);
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function line(string|int|float $price, int $quantity): int
    {
        $cents = self::cents($price);
        if ($quantity < 1 || $quantity > 2147483647 || ($cents > 0 && $quantity > intdiv(self::MAX_CENTS, $cents))) {
            throw ValidationException::withMessages(['cart' => 'Giá trị giỏ hàng vượt giới hạn. Vui lòng giảm số lượng.']);
        }

        return $cents * $quantity;
    }

    public static function total(array $cart): string
    {
        $total = 0;
        foreach ($cart as $item) {
            $line = self::line($item['price'], (int) $item['quantity']);
            if ($line > self::MAX_CENTS - $total) {
                throw ValidationException::withMessages(['cart' => 'Tổng giỏ hàng vượt giới hạn. Vui lòng giảm số lượng.']);
            }
            $total += $line;
        }

        return self::decimal($total);
    }

    public static function format(string|int|float|null $value): string
    {
        if ($value === null) {
            return '—';
        }
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');
        $formatted = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);
        $fraction = str_pad($fraction, 2, '0');

        return $formatted.($fraction === '00' ? '' : ','.$fraction);
    }

    public static function formattedLine(string|int|float $price, int $quantity): string
    {
        try {
            return self::format(self::decimal(self::line($price, $quantity)));
        } catch (ValidationException $exception) {
            return '—';
        }
    }
}
