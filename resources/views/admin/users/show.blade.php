@extends('layouts.admin')

@section('title', 'Chi tiết khách hàng')

@section('admin-content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h2 mb-1">Chi tiết khách hàng</h1>
        <div class="text-muted">{{ $user->name }} · {{ $user->email }}</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">Quay lại danh sách</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4"><div class="card h-100"><div class="card-body">
        <div class="text-muted">Tổng số đơn hàng</div><strong class="fs-3">{{ $orderCount }}</strong>
    </div></div></div>
    <div class="col-sm-6 col-lg-4"><div class="card h-100"><div class="card-body">
        <div class="text-muted">Giá trị đơn hợp lệ (đã hoàn thành)</div>
        <strong class="fs-3">{{ \App\Support\Money::format($validOrderTotal) }} đ</strong>
    </div></div></div>
    <div class="col-sm-6 col-lg-4"><div class="card h-100"><div class="card-body">
        <div class="text-muted">Ngày tạo tài khoản</div><strong>{{ $user->created_at?->format('d/m/Y H:i') ?? '—' }}</strong>
    </div></div></div>
</div>

<h2 class="h4 mb-3">Lịch sử mua hàng</h2>
<div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
        <thead class="table-dark"><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Sản phẩm</th><th>Tổng tiền</th><th>Trạng thái</th></tr></thead>
        <tbody>
        @forelse($orders as $order)
            <tr>
                <td>#{{ $order->id }}</td>
                <td>{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                <td>
                    @forelse($order->items as $item)
                        <div>{{ $item->product_name }} × {{ $item->quantity }}</div>
                    @empty
                        <span class="text-muted">Không có chi tiết sản phẩm</span>
                    @endforelse
                </td>
                <td class="text-nowrap">
                    <div>{{ \App\Support\Money::format($order->total_amount) }} đ</div>
                    @if ($order->voucher_code || \App\Support\Money::cents($order->discount_amount ?? '0.00') > 0)
                        <div class="small text-success">
                            Voucher <code>{{ $order->voucher_code ?? '—' }}</code> (−{{ \App\Support\Money::format($order->discount_amount) }} đ)
                        </div>
                    @endif
                </td>
                <td>{{ ['pending' => 'Chờ xử lý', 'processing' => 'Đang xử lý', 'completed' => 'Đã hoàn thành', 'cancelled' => 'Đã hủy'][$order->status] ?? $order->status }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted">Khách hàng chưa có đơn hàng.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $orders->links() }}
@endsection
