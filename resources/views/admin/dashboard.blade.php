@extends('layouts.admin')
@section('title', 'Tổng quan quản trị - SneakerShop')
@section('admin-content')
<h1 class="h2 fw-bold">Tổng quan cửa hàng</h1>
<p class="text-muted mb-4">Xin chào {{ auth()->user()->name }}. Đây là tình hình hiện tại của SneakerShop.</p>
<div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-4">
    @foreach([
        ['Sản phẩm', $productCount, 'admin.products.index'],
        ['Danh mục', $categoryCount, 'admin.categories.index'],
        ['Đơn hàng', $orderCount, 'admin.orders.index'],
        ['Chờ xử lý', $pendingOrderCount, 'admin.orders.index'],
    ] as [$label, $count, $target])
        <div class="col"><a class="card stat-card h-100 text-decoration-none text-dark p-3" href="{{ route($target, $label === 'Chờ xử lý' ? ['status' => 'pending'] : []) }}"><span class="small text-muted">{{ $label }}</span><strong class="fs-2">{{ $count }}</strong></a></div>
    @endforeach
</div>
<div class="card border-0 p-4 mb-4">
    <span class="text-muted">Tổng giá trị đơn đã hoàn thành</span>
    <strong class="fs-3">{{ number_format($completedRevenue, 0, ',', '.') }} đ</strong>
</div>
<div class="card border-0 p-3 p-lg-4">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h2 class="h5 mb-0">Đơn hàng gần đây</h2><a href="{{ route('admin.orders.index') }}">Xem tất cả</a></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Tổng tiền</th><th>Trạng thái</th></tr></thead>
            <tbody>
                @forelse($recentOrders as $order)
                    <tr><td>#{{ $order->id }}</td><td>{{ $order->customer_name }}</td><td>{{ number_format($order->total_amount, 0, ',', '.') }} đ</td><td>{{ ['pending' => 'Chờ xử lý', 'processing' => 'Đang xử lý', 'completed' => 'Đã hoàn thành', 'cancelled' => 'Đã hủy'][$order->status] ?? $order->status }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">Chưa có đơn hàng nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
