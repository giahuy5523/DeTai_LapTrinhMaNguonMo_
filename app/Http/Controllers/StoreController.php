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

        $keyword = $validated['keyword'] ?? '';

        $products = Product::where('is_active', true)
            ->where('quantity', '>', 0)
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where('name', 'like', '%'.$keyword.'%');
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate(12)
            ->appends($request->only('keyword'));

        return view('store.index', compact('products', 'keyword'));
    }
}
