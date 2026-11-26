<?php

namespace App\Support;

use App\Models\Voucher;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class VoucherService
{
    public static function normalizeCode(?string $code): string
    {
        return is_string($code) ? mb_strtoupper(trim($code)) : '';
    }

    /**
     * Validate and compute voucher discount without consuming a use.
     *
     * @return array{
     *     voucher_id: int,
     *     code: string,
     *     discount_type: string,
     *     discount_value: string,
     *     max_discount_amount: ?string,
     *     subtotal_cents: int,
     *     subtotal_amount: string,
     *     discount_cents: int,
     *     discount_amount: string,
     *     payable_cents: int,
     *     payable_amount: string
     * }
     */
    public function quote(string $code, int $subtotalCents, ?CarbonInterface $at = null): array
    {
        $normalized = self::normalizeCode($code);
        if ($normalized === '') {
            $this->reject('Vui lòng nhập mã voucher.');
        }

        $voucher = Voucher::where('code', $normalized)->first();

        return $this->evaluate($voucher, $subtotalCents, $at ?? now());
    }

    /**
     * Revalidate under row lock and atomically increment used_count inside the caller's order transaction.
     *
     * @return array{
     *     voucher_id: int,
     *     code: string,
     *     discount_type: string,
     *     discount_value: string,
     *     max_discount_amount: ?string,
     *     subtotal_cents: int,
     *     subtotal_amount: string,
     *     discount_cents: int,
     *     discount_amount: string,
     *     payable_cents: int,
     *     payable_amount: string
     * }
     */
    public function consume(string $code, int $subtotalCents, ?CarbonInterface $at = null): array
    {
        $normalized = self::normalizeCode($code);
        if ($normalized === '') {
            $this->reject('Vui lòng nhập mã voucher.');
        }

        $voucher = Voucher::where('code', $normalized)->lockForUpdate()->first();
        $quote = $this->evaluate($voucher, $subtotalCents, $at ?? now());

        $updated = Voucher::whereKey($voucher->id)
            ->when(
                $voucher->usage_limit !== null,
                fn ($query) => $query->whereColumn('used_count', '<', 'usage_limit')
            )
            ->increment('used_count');

        if ($updated !== 1) {
            $this->reject('Mã voucher này đã hết lượt sử dụng.');
        }

        return $quote;
    }

    /**
     * @return array{
     *     voucher_id: int,
     *     code: string,
     *     discount_type: string,
     *     discount_value: string,
     *     max_discount_amount: ?string,
     *     subtotal_cents: int,
     *     subtotal_amount: string,
     *     discount_cents: int,
     *     discount_amount: string,
     *     payable_cents: int,
     *     payable_amount: string
     * }
     */
    private function evaluate(?Voucher $voucher, int $subtotalCents, CarbonInterface $now): array
    {
        if ($subtotalCents < 0 || $subtotalCents > Money::MAX_CENTS) {
            $this->reject('Giá trị đơn hàng không hợp lệ để áp dụng voucher.');
        }

        if (! $voucher) {
            $this->reject('Mã voucher không tồn tại.');
        }

        if (! $voucher->is_active) {
            $this->reject('Mã voucher này hiện đang tạm khóa.');
        }

        if ($voucher->starts_at && $now->lt($voucher->starts_at)) {
            $this->reject('Mã voucher này chưa đến thời gian áp dụng.');
        }

        if ($voucher->ends_at && $now->gt($voucher->ends_at)) {
            $this->reject('Mã voucher này đã hết hạn.');
        }

        $minOrderCents = Money::cents($voucher->minimum_order_amount);
        if ($subtotalCents < $minOrderCents) {
            $this->reject('Đơn hàng cần tối thiểu '.Money::format($voucher->minimum_order_amount).' đ để dùng mã '.$voucher->code.'.');
        }

        if ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit) {
            $this->reject('Mã voucher này đã hết lượt sử dụng.');
        }

        $discountCents = $this->calculateDiscountCents($voucher, $subtotalCents);
        $payableCents = $subtotalCents - $discountCents;

        return [
            'voucher_id' => $voucher->id,
            'code' => $voucher->code,
            'discount_type' => $voucher->discount_type,
            'discount_value' => (string) $voucher->discount_value,
            'max_discount_amount' => $voucher->max_discount_amount !== null ? (string) $voucher->max_discount_amount : null,
            'subtotal_cents' => $subtotalCents,
            'subtotal_amount' => Money::decimal($subtotalCents),
            'discount_cents' => $discountCents,
            'discount_amount' => Money::decimal($discountCents),
            'payable_cents' => $payableCents,
            'payable_amount' => Money::decimal($payableCents),
        ];
    }

    private function calculateDiscountCents(Voucher $voucher, int $subtotalCents): int
    {
        if ($voucher->discount_type === 'percentage') {
            // discount_value has up to 2 decimal places (0.00..100.00), so cents() yields basis points (0..10000).
            $rateBasisPoints = Money::cents($voucher->discount_value);
            if ($rateBasisPoints < 0 || $rateBasisPoints > 10000) {
                $this->reject('Cấu hình phần trăm giảm của voucher không hợp lệ.');
            }

            // Overflow-safe exact floor(subtotalCents * rateBasisPoints / 10000).
            $discountCents = intdiv($subtotalCents, 10000) * $rateBasisPoints
                + intdiv(($subtotalCents % 10000) * $rateBasisPoints, 10000);

            if ($voucher->max_discount_amount !== null) {
                $discountCents = min($discountCents, Money::cents($voucher->max_discount_amount));
            }

            return min($discountCents, $subtotalCents);
        }

        if ($voucher->discount_type === 'fixed') {
            $fixedCents = Money::cents($voucher->discount_value);

            return min($fixedCents, $subtotalCents);
        }

        $this->reject('Loại voucher không được hỗ trợ.');
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['voucher_code' => $message]);
    }
}
