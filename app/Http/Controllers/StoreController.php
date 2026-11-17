<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        // Chuẩn hóa từ khóa để chuỗi chỉ chứa khoảng trắng không tạo tìm kiếm giả.
        $keyword = trim($validated['keyword'] ?? '');

        $products = Product::where('is_active', true)
            ->where('quantity', '>', 0)
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where('name', 'like', '%'.$keyword.'%');
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate(12)
            // Chỉ giữ từ khóa đã chuẩn hóa trong các liên kết phân trang.
            ->appends($keyword !== '' ? ['keyword' => $keyword] : []);

        return view('store.index', compact('products', 'keyword'));
    }
}
