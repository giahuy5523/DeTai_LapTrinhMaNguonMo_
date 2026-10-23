<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'productCount' => Product::count(),
            'categoryCount' => Category::count(),
            'orderCount' => Order::count(),
            'pendingOrderCount' => Order::where('status', 'pending')->count(),
            'completedRevenue' => Order::where('status', 'completed')->sum('total_amount'),
            'recentOrders' => Order::latest()->orderByDesc('id')->take(5)->get(),
        ]);
    }
}
