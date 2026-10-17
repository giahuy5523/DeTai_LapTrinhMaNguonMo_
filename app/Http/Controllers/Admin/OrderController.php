<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $validated = $request->validate([
            'status' => [
                'required',
                'in:pending,processing,completed,cancelled',
            ],
        ], [
            'status.required' => 'Vui lòng chọn trạng thái!',
            'status.in' => 'Trạng thái không hợp lệ!',
        ]);

        $newStatus = $validated['status'];

        DB::transaction(function () use ($order, $newStatus) {
            // Khóa đơn hàng để tránh cập nhật đồng thời
            $lockedOrder = Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = $lockedOrder->status;

            // Không cho thay đổi đơn đã hủy hoặc hoàn thành
            if (in_array($oldStatus, ['cancelled', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Đơn hàng đã hủy hoặc hoàn thành, không thể thay đổi trạng thái.',
                ]);
            }

            // Không cho gửi lại trạng thái hiện tại
            if ($oldStatus === $newStatus) {
                return;
            }

            // Chỉ cho phép các bước chuyển trạng thái hợp lệ
            $allowedTransitions = [
                'pending' => ['processing', 'cancelled'],
                'processing' => ['completed', 'cancelled'],
            ];

            if (
                !isset($allowedTransitions[$oldStatus]) ||
                !in_array(
                    $newStatus,
                    $allowedTransitions[$oldStatus],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Không thể chuyển từ trạng thái '
                        . $oldStatus . ' sang ' . $newStatus . '.',
                ]);
            }

            // Chỉ hoàn kho khi đơn chuyển sang cancelled lần đầu
            if ($newStatus === 'cancelled') {
                $items = $lockedOrder->items;

                foreach ($items as $item) {
                    if (!$item->product_id) {
                        continue;
                    }

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
            }

            // Cập nhật trạng thái sau khi kiểm tra và xử lý tồn kho
            $lockedOrder->status = $newStatus;
            $lockedOrder->save();
        });

        return redirect()->back()->with(
            'success',
            'Cập nhật trạng thái đơn hàng #' . $order->id . ' thành công!'
        );
    }
}
