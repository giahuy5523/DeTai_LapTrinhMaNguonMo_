<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    // Hiển thị giỏ hàng
    public function index()
    {
        $cart = session()->get('cart', []);

        $total = collect($cart)->sum(function ($item) {
            return $item['price'] * $item['quantity'];
        });

        return view('cart.index', compact('cart', 'total'));
    }

    // Thêm sản phẩm vào giỏ
    public function add(Request $request, $id)
    {
        $product = Product::where('is_active', true)->findOrFail($id);

        $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $quantity = (int) $request->input('quantity', 1);

        if ($quantity > $product->quantity) {
            return back()->with('error', 'Số lượng vượt quá tồn kho.');
        }

        $cart = session()->get('cart', []);

        $currentQuantity = $cart[$id]['quantity'] ?? 0;
        $newQuantity = $currentQuantity + $quantity;

        if ($newQuantity > $product->quantity) {
            return back()->with('error', 'Giỏ hàng vượt quá số lượng tồn kho.');
        }

        $cart[$id] = [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'image' => $product->image,
            'quantity' => $newQuantity,
        ];

        session()->put('cart', $cart);

        return redirect()->route('cart.index')
            ->with('success', 'Đã thêm sản phẩm vào giỏ hàng.');
    }

    // Cập nhật số lượng
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = session()->get('cart', []);

        if (!isset($cart[$id])) {
            return redirect()->route('cart.index')
                ->with('error', 'Sản phẩm không có trong giỏ hàng.');
        }

        $product = Product::findOrFail($id);
        $quantity = (int) $request->quantity;

        if ($quantity > $product->quantity) {
            return back()->with('error', 'Số lượng vượt quá tồn kho.');
        }

        $cart[$id]['quantity'] = $quantity;

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
}