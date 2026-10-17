<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // Hiển thị danh sách đơn hàng và lọc theo trạng thái
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

    // Cập nhật trạng thái đơn hàng
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
        ], [
            'status.required' => 'Vui lòng chọn trạng thái!',
            'status.in' => 'Trạng thái không hợp lệ!',
        ]);

        DB::transaction(function () use ($request, $order) {
            $order = Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = $order->status;
            $newStatus = $request->status;

            // Chỉ hoàn kho khi đơn chuyển sang cancelled lần đầu
            if ($oldStatus !== 'cancelled' && $newStatus === 'cancelled') {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        $product = Product::whereKey($item->product_id)
                            ->lockForUpdate()
                            ->first();

                        if ($product) {
                            $product->increment('quantity', $item->quantity);
                        }
                    }
                }
            }

            $order->update([
                'status' => $newStatus,
            ]);
        });

        return redirect()->back()->with(
            'success',
            'Cập nhật trạng thái đơn hàng #' . $order->id . ' thành công!'
        );
    }
}
