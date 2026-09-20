<?php

namespace App\Http\Controllers;

use App\Models\Product;

class StoreController extends Controller
{
    public function index()
    {
        $products = Product::where('is_active', true)
            ->where('quantity', '>', 0)
            ->latest()
            ->paginate(12);

        return view('store.index', compact('products'));
    }
}