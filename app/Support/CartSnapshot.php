<?php

namespace App\Support;

use App\Models\Product;

final class CartSnapshot
{
    public static function productId(string|int $value): ?int
    {
        $digits = (string) $value;
        if (! preg_match('/^[0-9]+$/D', $digits)) {
            return null;
        }
        $digits = ltrim($digits, '0');
        $max = (string) PHP_INT_MAX;
        if ($digits === '' || strlen($digits) > strlen($max)
            || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            return null;
        }

        return (int) $digits;
    }

    public static function normalize(array $cart): array
    {
        $normalized = [];
        foreach ($cart as $key => $item) {
            $id = self::productId($key);
            $quantity = is_array($item) ? filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT) : false;
            if ($id === null || ! $quantity || $quantity < 1) {
                continue;
            }
            if (isset($normalized[$id])) {
                // Keep an oversized legacy quantity editable, without overflowing PHP integers.
                $current = $normalized[$id]['quantity'];
                $normalized[$id]['quantity'] = $quantity > PHP_INT_MAX - $current ? PHP_INT_MAX : $current + $quantity;
            } else {
                $normalized[$id] = $item;
                $normalized[$id]['quantity'] = $quantity;
            }
        }

        return $normalized;
    }

    public static function refresh(array $cart): array
    {
        $normalized = self::normalize($cart);
        if ($normalized !== $cart) {
            session()->forget('checkout_quote');
            session()->flash('warning', 'Giỏ hàng đã được chuẩn hóa: gộp sản phẩm trùng và loại mục không hợp lệ. Vui lòng kiểm tra lại số lượng.');
        }
        $cart = $normalized;
        $products = Product::whereIn('id', array_keys($cart))->get()->keyBy('id');
        foreach ($cart as $id => &$item) {
            $product = $products->get($id);
            if (! $product) {
                $item = array_merge(['id' => $id, 'name' => 'Sản phẩm không còn tồn tại', 'price' => '0.00', 'image' => null], $item);
                $item['is_active'] = false;
                $item['stock'] = 0;

                continue;
            }
            $item = array_merge($item, [
                'id' => $product->id, 'name' => $product->name, 'price' => $product->price,
                'image' => $product->image, 'is_active' => (bool) $product->is_active,
                'stock' => (int) $product->quantity,
            ]);
        }
        unset($item);

        return $cart;
    }

    public static function version(array $cart): string
    {
        ksort($cart, SORT_NUMERIC);
        $snapshot = [];
        foreach ($cart as $id => $item) {
            $snapshot[] = [(int) $id, (int) $item['quantity'], Money::cents($item['price'])];
        }

        return hash_hmac('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), config('app.key'));
    }
}
