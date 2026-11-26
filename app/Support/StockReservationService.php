<?php

namespace App\Support;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StockReservationService
{
    public function reserve(int $userId, string $token, array $cart): array
    {
        return DB::transaction(function () use ($userId, $token, $cart) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $existing = DB::table('stock_reservations')->where('token', $token)->get();
            if ($existing->isNotEmpty()) {
                $this->validate($userId, $token, $cart);
                return ['token' => $token, 'expires_at' => $existing->first()->expires_at];
            }
            if (! preg_match('/^[a-f0-9]{64}$/D', $token) || $cart === [] || CartSnapshot::normalize($cart) !== $cart) {
                $this->reject('Giỏ hàng hoặc mã giữ hàng không hợp lệ.');
            }
            ksort($cart, SORT_NUMERIC);
            $products = Product::whereIn('id', array_keys($cart))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $expires = now()->addMinutes(5);
            foreach ($cart as $id => $item) {
                $product = $products->get($id);
                $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
                if (! $product || ! $product->is_active || ! $quantity || $quantity < 1 || $quantity > 2147483647) {
                    $this->reject('Sản phẩm hoặc số lượng không hợp lệ.');
                }
                $held = DB::table('stock_reservations')->where('product_id', $id)
                    ->where('status', 'active')->where('expires_at', '>', now())->lockForUpdate()->get()->sum('quantity');
                if ($quantity > $product->quantity - $held) {
                    $this->reject('Sản phẩm "'.$product->name.'" không đủ hàng khả dụng.');
                }
                DB::table('stock_reservations')->insert([
                    'user_id' => $userId, 'token' => $token, 'product_id' => $id,
                    'quantity' => $quantity, 'status' => 'active', 'expires_at' => $expires,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return ['token' => $token, 'expires_at' => $expires->toDateTimeString()];
        }, 3);
    }

    public function validate(int $userId, string $token, array $cart): void
    {
        $rows = DB::table('stock_reservations')->where('token', $token)->orderBy('product_id')->lockForUpdate()->get();
        if ($rows->isEmpty() || $rows->count() !== count($cart)) {
            $this->reject('Lượt giữ hàng không còn khớp giỏ hàng.');
        }
        foreach ($rows as $row) {
            if ((int) $row->user_id !== $userId || $row->status !== 'active'
                || now()->greaterThanOrEqualTo($row->expires_at)
                || (int) $row->quantity !== (int) ($cart[$row->product_id]['quantity'] ?? 0)) {
                $this->reject('Lượt giữ hàng đã hết hạn hoặc thay đổi. Vui lòng thử lại.');
            }
        }
    }

    // The caller creates the order and decrements stock in this same transaction.
    public function consume(int $userId, string $token, array $cart, callable $createOrder): mixed
    {
        return DB::transaction(function () use ($userId, $token, $cart, $createOrder) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            Product::whereIn('id', array_keys($cart))->orderBy('id')->lockForUpdate()->get();
            $this->validate($userId, $token, $cart);
            $result = $createOrder();
            DB::table('stock_reservations')->where('user_id', $userId)->where('token', $token)
                ->where('status', 'active')->update(['status' => 'consumed', 'updated_at' => now()]);
            return $result;
        }, 3);
    }

    public function cancel(int $userId, string $token): void
    {
        DB::transaction(function () use ($userId, $token) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            DB::table('stock_reservations')->where('user_id', $userId)->where('token', $token)
                ->where('status', 'active')->update(['status' => 'cancelled', 'updated_at' => now()]);
        }, 3);
    }

    public function expire(): int
    {
        return DB::table('stock_reservations')->where('status', 'active')->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['cart' => $message]);
    }
}