<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

final class OrderStock
{
    public static function restore(Order $order): void
    {
        $items = $order->items()->whereNotNull('product_id')->orderBy('product_id')->get();
        foreach ($items as $item) {
            $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
            if (! $product) {
                continue;
            }
            if ($product->quantity > 2147483647 - $item->quantity) {
                throw ValidationException::withMessages([
                    'stock' => 'Tồn kho vượt giới hạn hoàn hàng. Vui lòng liên hệ quản trị viên điều chỉnh tồn kho.',
                ]);
            }
            $product->increment('quantity', (int) $item->quantity);
        }
    }
}
