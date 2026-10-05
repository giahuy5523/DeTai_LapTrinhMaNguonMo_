@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 my-4">
    <h2 class="mb-4">Quản Lý Trạng Thái Đơn Hàng</h2>

    {{-- Thông báo thành công --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Form Lọc trạng thái đơn hàng -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form action="{{ route('admin.orders.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="fw-bold">Lọc theo trạng thái:</label>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">-- Tất cả trạng thái --</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Chờ xử lý (Pending)</option>
                        <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Đang xử lý (Processing)</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Đã hoàn thành (Completed)</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Đã hủy (Cancelled)</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary">Lọc</button>
                    @if(request('status'))
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">Xóa lọc</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Bảng Danh sách Đơn hàng -->
    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th width="80">Mã ĐH</th>
                        <th>Khách hàng</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái hiện tại</th>
                        <th width="280">Cập nhật trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="fw-bold">#{{ $order->id }}</td>
                            <td>{{ $order->customer_name ?? 'Khách vãng lai' }}</td>
                            <td class="text-danger fw-bold">{{ number_format($order->total_amount ?? $order->total_price ?? 0) }} VNĐ</td>
                            <td>
                                @switch($order->status)
                                    @case('pending')
                                        <span class="badge bg-warning text-dark">Chờ xử lý</span>
                                        @break
                                    @case('processing')
                                        <span class="badge bg-info text-dark">Đang xử lý</span>
                                        @break
                                    @case('completed')
                                        <span class="badge bg-success">Đã hoàn thành</span>
                                        @break
                                    @case('cancelled')
                                        <span class="badge bg-danger">Đã hủy</span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary">{{ $order->status }}</span>
                                @endswitch
                            </td>
                            <td>
                                <!-- Form cập nhật trạng thái trực tiếp -->
                                <form action="{{ route('admin.orders.updateStatus', $order->id) }}" method="POST" class="d-flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="pending" {{ $order->status == 'pending' ? 'selected' : '' }}>Chờ xử lý</option>
                                        <option value="processing" {{ $order->status == 'processing' ? 'selected' : '' }}>Đang xử lý</option>
                                        <option value="completed" {{ $order->status == 'completed' ? 'selected' : '' }}>Đã hoàn thành</option>
                                        <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>Đã hủy</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Không tìm thấy đơn hàng nào!</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Thanh Phân Trang -->
            <div class="d-flex justify-content-end mt-3">
                {{ $orders->appends(request()->all())->links() }}
            </div>
        </div>
    </div>
</div>
@endsection