@extends('layouts.admin')

@section('title', 'Quản lý voucher')

@section('admin-content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h2 mb-1">Quản lý voucher</h1>
        <p class="text-muted mb-0">Mã giảm giá dùng chung cho checkout.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('admin.vouchers.create') }}">Tạo voucher</a>
</div>

<div class="card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Mã</th><th>Hình thức</th><th>Đơn tối thiểu</th><th>Lượt dùng</th><th>Hiệu lực</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
            <tbody>
            @forelse($vouchers as $voucher)
                <tr>
                    <td class="fw-bold">{{ $voucher->code }}</td>
                    <td>
                        @if($voucher->discount_type === 'percentage')
                            {{ rtrim(rtrim($voucher->discount_value, '0'), '.') }}%
                            <div class="small text-muted">Tối đa {{ \App\Support\Money::format($voucher->max_discount_amount) }} đ</div>
                        @else
                            {{ \App\Support\Money::format($voucher->discount_value) }} đ
                        @endif
                    </td>
                    <td>{{ \App\Support\Money::format($voucher->minimum_order_amount) }} đ</td>
                    <td>{{ $voucher->used_count }} / {{ $voucher->usage_limit ?? '∞' }}</td>
                    <td class="text-nowrap">{{ $voucher->starts_at->format('d/m/Y H:i') }}<br>{{ $voucher->ends_at->format('d/m/Y H:i') }}</td>
                    @php
                        if (! $voucher->is_active) {
                            $availability = ['Đã tắt', 'text-bg-secondary'];
                        } elseif (now()->lt($voucher->starts_at)) {
                            $availability = ['Chưa đến hạn', 'text-bg-warning'];
                        } elseif (now()->gt($voucher->ends_at)) {
                            $availability = ['Hết hạn', 'text-bg-danger'];
                        } elseif ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit) {
                            $availability = ['Hết lượt', 'text-bg-danger'];
                        } else {
                            $availability = ['Đang bật', 'text-bg-success'];
                        }
                    @endphp
                    <td><span class="badge {{ $availability[1] }}">{{ $availability[0] }}</span></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.vouchers.edit', $voucher) }}">Sửa</a>
                        <form class="d-inline" method="POST" action="{{ route('admin.vouchers.destroy', $voucher) }}" onsubmit="return confirm('Xóa voucher này? Đơn cũ vẫn giữ mã và số tiền giảm đã lưu.');">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Chưa có voucher.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $vouchers->links() }}
</div></div>
@endsection
