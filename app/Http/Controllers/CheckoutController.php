<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Support\CartSnapshot;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    // Hiển thị trang thanh toán
    public function index()
    {
        $cart = CartSnapshot::refresh(session()->get('cart', []));
        session()->put('cart', $cart);

        if (empty($cart)) {
            return redirect()->route('cart.index')
                ->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        try {
            $total = Money::total($cart);
        } catch (ValidationException $exception) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Giá trị giỏ hàng vượt giới hạn. Vui lòng giảm số lượng.']);
        }
        $checkoutVersion = bin2hex(random_bytes(32));
        session()->put('checkout_quote', ['version' => $checkoutVersion, 'snapshot' => CartSnapshot::version($cart)]);

        return view('checkout.index', compact('cart', 'total', 'checkoutVersion'));
    }

    // Xử lý đặt hàng
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9 .()\-]{7,18}[0-9]$/D', function ($attribute, $value, $fail) {
                if (is_string($value) && (preg_match_all('/[0-9]/', $value) < 9 || preg_match_all('/[0-9]/', $value) > 15)) {
                    $fail('Số điện thoại phải chứa từ 9 đến 15 chữ số.');
                }
            }],
            'checkout_version' => ['required', 'string', 'size:64'],
            'address' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', 'in:cod,bank_transfer'],
        ]);

        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')
                ->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        if (CartSnapshot::normalize($cart) !== $cart) {
            session()->put('cart', CartSnapshot::refresh($cart));
            session()->forget('checkout_quote');
            throw ValidationException::withMessages(['cart' => 'Giỏ hàng đã được chuẩn hóa. Vui lòng xem lại trang thanh toán.']);
        }
        $quote = session('checkout_quote');
        if (! $quote || ! hash_equals($quote['version'], $validated['checkout_version'])
            || ! hash_equals($quote['snapshot'], CartSnapshot::version($cart))) {
            throw ValidationException::withMessages(['cart' => 'Giỏ hàng đã thay đổi. Vui lòng xem lại trang thanh toán.']);
        }
        // A single lock order prevents opposite cart insertion orders causing deadlocks.
        ksort($cart, SORT_NUMERIC);

        try {
            $order = Order::where('checkout_token', $validated['checkout_version'])->where('user_id', auth()->id())->first()
                ?? DB::transaction(function () use ($validated, $cart) {
                    $orderItems = [];
                    $seenProducts = [];
                    $total = 0;

                    // Kiểm tra giá, trạng thái bán và tồn kho từ database
                    foreach ($cart as $id => $item) {
                        $quantity = filter_var(
                            $item['quantity'] ?? null,
                            FILTER_VALIDATE_INT
                        );

                        if (! $quantity || $quantity < 1) {
                            throw ValidationException::withMessages([
                                'cart' => 'Số lượng sản phẩm trong giỏ hàng không hợp lệ.',
                            ]);
                        }

                        $product = Product::where('is_active', true)
                            ->whereKey($id)
                            ->lockForUpdate()
                            ->first();

                        if (! $product) {
                            throw ValidationException::withMessages([
                                'cart' => 'Một sản phẩm không còn tồn tại hoặc đã ngừng bán.',
                            ]);
                        }

                        if (isset($seenProducts[$product->id])) {
                            throw ValidationException::withMessages(['cart' => 'Giỏ hàng chứa sản phẩm trùng. Vui lòng mở lại giỏ hàng.']);
                        }
                        $seenProducts[$product->id] = true;

                        if ($quantity > $product->quantity) {
                            throw ValidationException::withMessages([
                                'cart' => 'Sản phẩm "'.$product->name
                                    .'" không đủ số lượng trong kho. Hiện còn: '
                                    .$product->quantity.'.',
                            ]);
                        }

                        if (Money::cents($product->price) !== Money::cents($item['price'])) {
                            throw ValidationException::withMessages(['cart' => 'Giá sản phẩm đã thay đổi. Vui lòng mở lại trang thanh toán để xác nhận giá mới.']);
                        }
                        $subtotal = Money::line($product->price, $quantity);
                        if ($subtotal > Money::MAX_CENTS - $total) {
                            throw ValidationException::withMessages(['cart' => 'Tổng giá trị đơn vượt giới hạn. Vui lòng giảm số lượng.']);
                        }
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
                        'total_amount' => Money::decimal($total),
                        'payment_method' => $validated['payment_method'],
                        'checkout_token' => $validated['checkout_version'],
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
                            'subtotal' => Money::decimal($item['subtotal']),
                        ]);

                        $product->decrement('quantity', $item['quantity']);
                    }

                    return $order;
                }, 3);
        } catch (QueryException|ValidationException $exception) {
            if ($exception instanceof QueryException && ! str_contains($exception->getMessage(), 'checkout_token')) {
                throw $exception;
            }

            // A concurrent replay can see depleted stock before reaching the unique index.
            // The transaction has rolled back here; read the committed order outside its snapshot.
            $duplicate = Order::where('checkout_token', $validated['checkout_version'])
                ->where('user_id', auth()->id())->first();
            if (! $duplicate) {
                throw $exception;
            }
            $order = $duplicate;
        }

        // Chỉ xóa giỏ hàng sau khi transaction thành công
        session()->forget(['cart', 'checkout_quote']);

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

        if (! $orderId) {
            return redirect()->route('store.index');
        }

        $order = Order::whereKey($orderId)
            ->where('user_id', auth()->id())
            ->first();

        if (! $order) {
            return redirect()->route('store.index');
        }

        return view('checkout.success', compact('order'));
    }
}
