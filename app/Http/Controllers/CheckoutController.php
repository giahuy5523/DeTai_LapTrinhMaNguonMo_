<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    // Hiển thị trang checkout
    public function index()
    {
        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')
                ->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        $total = collect($cart)->sum(function ($item) {
            return $item['price'] * $item['quantity'];
        });

        return view('checkout.index', compact('cart', 'total'));
    }

    // Xử lý đặt hàng
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')
                ->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        try {
            $order = DB::transaction(function () use ($validated, $cart) {
                $products = [];
                $total = 0;

                // Kiểm tra sản phẩm và tồn kho mới nhất
                foreach ($cart as $id => $item) {
                    $product = Product::where('is_active', true)
                        ->lockForUpdate()
                        ->find($id);

                    if (!$product) {
                        throw ValidationException::withMessages([
                            'cart' => 'Một sản phẩm trong giỏ hàng không còn tồn tại hoặc đã ngừng bán.',
                        ]);
                    }

                    $quantity = (int) ($item['quantity'] ?? 0);

                    if ($quantity < 1 || $quantity > $product->quantity) {
                        throw ValidationException::withMessages([
                            'cart' => 'Sản phẩm "' . $product->name .
                                '" không đủ số lượng trong kho.',
                        ]);
                    }

                    $subtotal = $product->price * $quantity;
                    $total += $subtotal;

                    $products[] = [
                        'product' => $product,
                        'quantity' => $quantity,
                        'subtotal' => $subtotal,
                    ];
                }

                // Tạo đơn hàng
                $order = Order::create([
                    'user_id' => auth()->id(),
                    'customer_name' => $validated['customer_name'],
                    'phone' => $validated['phone'],
                    'address' => $validated['address'],
                    'note' => $validated['note'] ?? null,
                    'total_amount' => $total,
                    'status' => 'pending',
                ]);

                // Lưu chi tiết đơn hàng và trừ tồn kho
                foreach ($products as $item) {
                    $product = $item['product'];

                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->price,
                        'subtotal' => $item['subtotal'],
                    ]);

                    $product->decrement(
                        'quantity',
                        $item['quantity']
                    );
                }

                return $order;
            });
        } catch (ValidationException $e) {
            throw $e;
        }

        // Chỉ xóa giỏ sau khi transaction thành công
        session()->forget('cart');

        return redirect()->route('checkout.success')
            ->with('success', 'Đặt hàng thành công!')
            ->with('order_id', $order->id);
    }

    // Trang thông báo đặt hàng thành công
    public function success()
    {
        if (!session()->has('success')) {
            return redirect()->route('store.index');
        }

        return view('checkout.success', [
            'orderId' => session('order_id'),
        ]);
    }
}