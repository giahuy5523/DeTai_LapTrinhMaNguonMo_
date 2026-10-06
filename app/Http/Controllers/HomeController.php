<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        // Lấy 8 sản phẩm mới nhất để hiển thị ngoài trang chủ
        $newProducts = Product::with('category')->latest()->take(8)->get();
        
        return view('home', compact('newProducts'));
    }
}