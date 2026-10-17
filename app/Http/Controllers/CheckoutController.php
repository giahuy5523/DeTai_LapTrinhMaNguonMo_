<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    // Hiển thị trang thanh toán
    public function index()
    {
        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')
                ->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        $total = collect($cart)->sum(function ($item) {
            return (float) $item['price'] * (int) $item['quantity'];
        });

        return view('checkout.index', compact('cart', 'total'));
    }

    // Xử lý đặt hàng
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', 'in:cod,bank_transfer'],
        ]);

        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')
                ->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        $order = DB::transaction(function () use ($validated, $cart) {
            $orderItems = [];
            $total = 0;

            // Kiểm tra giá, trạng thái bán và tồn kho từ database
            foreach ($cart as $id => $item) {
                $quantity = filter_var(
                    $item['quantity'] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (!$quantity || $quantity < 1) {
                    throw ValidationException::withMessages([
                        'cart' => 'Số lượng sản phẩm trong giỏ hàng không hợp lệ.',
                    ]);
                }

                $product = Product::where('is_active', true)
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    throw ValidationException::withMessages([
                        'cart' => 'Một sản phẩm không còn tồn tại hoặc đã ngừng bán.',
                    ]);
                }

                if ($quantity > $product->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => 'Sản phẩm "' . $product->name
                            . '" không đủ số lượng trong kho. Hiện còn: '
                            . $product->quantity . '.',
                    ]);
                }

                $subtotal = (float) $product->price * $quantity;
                $total += $subtotal;

                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ];
            }

            // Tạo đơn hàng, giữ trạng thái ban đầu là pending
            $order = Order::create([
                'user_id' => auth()->id(),
                'customer_name' => $validated['customer_name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'note' => $validated['note'] ?? null,
                'total_amount' => $total,
                'payment_method' => $validated['payment_method'],
                'status' => 'pending',
            ]);

            // Lưu chi tiết đơn hàng và trừ tồn kho
            foreach ($orderItems as $item) {
                $product = $item['product'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $item['subtotal'],
                ]);

                $product->decrement('quantity', $item['quantity']);
            }

            return $order;
        });

        // Chỉ xóa giỏ hàng sau khi transaction thành công
        session()->forget('cart');

        // Lưu ID để trang thành công lấy đúng đơn hàng
        session()->put('last_checkout_order_id', $order->id);

        return redirect()->route('checkout.success')
            ->with('success', 'Đặt hàng thành công!')
            ->with('order_id', $order->id);
    }

    // Trang thông báo đặt hàng thành công
    public function success()
    {
        $orderId = session('last_checkout_order_id');

        if (!$orderId) {
            return redirect()->route('store.index');
        }

        $order = Order::whereKey($orderId)
            ->where('user_id', auth()->id())
            ->first();

        if (!$order) {
            return redirect()->route('store.index');
        }

        return view('checkout.success', compact('order'));
    }
}
