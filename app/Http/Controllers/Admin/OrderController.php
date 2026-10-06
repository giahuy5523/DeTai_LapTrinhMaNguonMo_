<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // 1. Hiển thị danh sách đơn hàng & Lọc theo trạng thái
    public function index(Request $request)
    {
        $status = $request->input('status');

        $orders = Order::when($status, function ($query, $status) {
            return $query->where('status', $status);
        })
        ->latest()
        ->paginate(10);

        return view('admin.orders.index', compact('orders', 'status'));
    }

    // 2. Cập nhật trạng thái đơn hàng
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
        ], [
            'status.required' => 'Vui lòng chọn trạng thái!',
            'status.in'       => 'Trạng thái không hợp lệ!',
        ]);

        $order->update([
            'status' => $request->status,
        ]);

        return redirect()->back()->with('success', 'Cập nhật trạng thái đơn hàng #' . $order->id . ' thành công!');
    }
}