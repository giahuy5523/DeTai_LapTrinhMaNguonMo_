<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Support\CartSnapshot;
use App\Support\Money;
use App\Support\OrderStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    // Hiển thị giỏ hàng và danh sách đơn hàng của khách
    public function index()
    {
        $cart = CartSnapshot::refresh(session()->get('cart', []));

        session()->put('cart', $cart);

        try {
            $total = Money::total($cart);
        } catch (ValidationException $exception) {
            $total = null;
            session()->flash('error', 'Giá trị giỏ hàng vượt giới hạn. Vui lòng giảm số lượng hoặc xóa bớt sản phẩm.');
        }

        // Ẩn đơn đã hủy khỏi danh sách đơn hàng gần đây
        $recentOrders = Order::with('items')
            ->where('user_id', auth()->id())
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->orderByDesc('id')
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
        $id = CartSnapshot::productId($id) ?? abort(404);
        $request->validate([
            'quantity' => ['sometimes', 'required', 'integer', 'min:1', 'max:2147483647'],
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

        $cart = CartSnapshot::refresh(session()->get('cart', []));
        session()->put('cart', $cart);

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

        Money::total($cart);
        session()->put('cart', $cart);
        session()->forget('checkout_quote');

        return redirect()->route('cart.index')
            ->with('success', 'Đã thêm sản phẩm vào giỏ hàng.');
    }

    // Cập nhật số lượng
    public function update(Request $request, $id)
    {
        $id = CartSnapshot::productId($id) ?? abort(404);
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ]);

        $cart = CartSnapshot::refresh(session()->get('cart', []));
        session()->put('cart', $cart);

        if (! isset($cart[$id])) {
            return redirect()->route('cart.index')
                ->with('error', 'Sản phẩm không có trong giỏ hàng.');
        }

        $product = Product::find($id);

        if (! $product) {
            return back()->with(
                'error',
                'Sản phẩm không còn tồn tại. Vui lòng xóa khỏi giỏ hàng.'
            );
        }

        if (! $product->is_active) {
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
                    .$product->quantity.'.'
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

        Money::total($cart);
        session()->put('cart', $cart);
        session()->forget('checkout_quote');

        return redirect()->route('cart.index')
            ->with('success', 'Đã cập nhật giỏ hàng.');
    }

    // Xóa sản phẩm khỏi giỏ
    public function remove($id)
    {
        $id = CartSnapshot::productId($id) ?? abort(404);
        $cart = CartSnapshot::refresh(session()->get('cart', []));

        unset($cart[$id]);

        session()->put('cart', $cart);
        session()->forget('checkout_quote');

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

            // Chỉ được hủy đơn đang chờ xử lý
            if ($order->status !== 'pending') {
                abort(
                    422,
                    'Chỉ có thể hủy đơn hàng đang chờ xử lý.'
                );
            }

            // Lấy chi tiết đơn hàng
            OrderStock::restore($order);

            // Giữ đơn trong database để bảo toàn lịch sử
            // và tránh tái sử dụng đơn đã hủy
            $order->status = 'cancelled';
            $order->save();
        }, 3);

        return redirect()->route('cart.index')
            ->with('success', 'Đã hủy đơn hàng thành công.');
    }
}
