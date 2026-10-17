<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    // Hiển thị giỏ hàng và danh sách đơn hàng của khách
    public function index()
    {
        $cart = session()->get('cart', []);

        foreach ($cart as $id => &$item) {
            $product = Product::find($id);

            if (!$product) {
                $item['is_active'] = false;
                $item['stock'] = 0;
                continue;
            }

            $item['id'] = $product->id;
            $item['name'] = $product->name;
            $item['price'] = $product->price;
            $item['image'] = $product->image;
            $item['is_active'] = (bool) $product->is_active;
            $item['stock'] = (int) $product->quantity;
        }

        unset($item);

        session()->put('cart', $cart);

        $total = collect($cart)->sum(function ($item) {
            return (float) ($item['price'] ?? 0)
                * (int) ($item['quantity'] ?? 0);
        });

        $recentOrders = Order::with('items')
            ->where('user_id', auth()->id())
            ->latest()
            ->take(10)
            ->get();

        return view('cart.index', compact(
            'cart',
            'total',
            'recentOrders'
        ));
    }

    // Thêm sản phẩm vào giỏ
    public function add(Request $request, $id)
    {
        $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::where('is_active', true)
            ->findOrFail($id);

        $quantity = (int) $request->input('quantity', 1);

        if ($quantity > $product->quantity) {
            return back()->with(
                'error',
                'Số lượng vượt quá tồn kho.'
            );
        }

        $cart = session()->get('cart', []);

        $currentQuantity = (int) ($cart[$id]['quantity'] ?? 0);
        $newQuantity = $currentQuantity + $quantity;

        if ($newQuantity > $product->quantity) {
            return back()->with(
                'error',
                'Giỏ hàng vượt quá số lượng tồn kho.'
            );
        }

        $cart[$id] = [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'image' => $product->image,
            'quantity' => $newQuantity,
            'is_active' => (bool) $product->is_active,
            'stock' => (int) $product->quantity,
        ];

        session()->put('cart', $cart);

        return redirect()->route('cart.index')
            ->with('success', 'Đã thêm sản phẩm vào giỏ hàng.');
    }

    // Cập nhật số lượng
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = session()->get('cart', []);

        if (!isset($cart[$id])) {
            return redirect()->route('cart.index')
                ->with('error', 'Sản phẩm không có trong giỏ hàng.');
        }

        $product = Product::find($id);

        if (!$product) {
            return back()->with(
                'error',
                'Sản phẩm không còn tồn tại. Vui lòng xóa khỏi giỏ hàng.'
            );
        }

        if (!$product->is_active) {
            return back()->with(
                'error',
                'Sản phẩm đã ngừng bán. Vui lòng xóa khỏi giỏ hàng.'
            );
        }

        $quantity = (int) $validated['quantity'];

        if ($quantity > $product->quantity) {
            return back()->with(
                'error',
                'Số lượng vượt quá tồn kho. Hiện còn: '
                    . $product->quantity . '.'
            );
        }

        $cart[$id] = [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'image' => $product->image,
            'quantity' => $quantity,
            'is_active' => (bool) $product->is_active,
            'stock' => (int) $product->quantity,
        ];

        session()->put('cart', $cart);

        return redirect()->route('cart.index')
            ->with('success', 'Đã cập nhật giỏ hàng.');
    }

    // Xóa sản phẩm khỏi giỏ
    public function remove($id)
    {
        $cart = session()->get('cart', []);

        unset($cart[$id]);

        session()->put('cart', $cart);

        return redirect()->route('cart.index')
            ->with('success', 'Đã xóa sản phẩm khỏi giỏ hàng.');
    }

    // Hủy đơn hàng đang chờ xử lý và hoàn lại tồn kho
    public function cancelOrder($id)
    {
        DB::transaction(function () use ($id) {
            $order = Order::where('user_id', auth()->id())
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== 'pending') {
                abort(
                    422,
                    'Chỉ có thể hủy đơn hàng đang chờ xử lý.'
                );
            }

            // Khóa chi tiết đơn hàng để xử lý trong transaction
            $orderItems = $order->items;

            foreach ($orderItems as $item) {
                $product = Product::whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($product) {
                    $product->increment(
                        'quantity',
                        (int) $item->quantity
                    );
                }
            }

            $order->status = 'cancelled';
            $order->save();
        });

        return redirect()->route('cart.index')
            ->with('success', 'Đã hủy đơn hàng thành công.');
    }
}
