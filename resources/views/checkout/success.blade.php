
@extends('layouts.app')

@section('title', 'Đặt hàng thành công')

@section('content')
<div class="container py-5">
    <div class="card mx-auto shadow-sm" style="max-width: 720px">
        <div class="card-body p-4 p-md-5 text-center">
            <div class="text-success mb-3">
                <span style="font-size: 56px" aria-hidden="true">✓</span>
            </div>

            <h2 class="text-success mb-3">Đặt hàng thành công!</h2>

            <p class="text-muted">
                Cảm ơn bạn đã mua hàng. Đơn hàng đã được ghi nhận.
            </p>

            <hr>

            <div class="text-start">
                <p>
                    <strong>Mã đơn hàng:</strong>
                    #{{ $order->id }}
                </p>

                <p>
                    <strong>Người nhận:</strong>
                    {{ $order->customer_name }}
                </p>

                <p>
                    <strong>Số điện thoại:</strong>
                    {{ $order->phone }}
                </p>

                <p>
                    <strong>Địa chỉ:</strong>
                    {{ $order->address }}
                </p>

                <p>
                    <strong>Trạng thái đơn hàng:</strong>
                    @php
                        $statusLabels = [
                            'pending' => ['Chờ xử lý', 'bg-warning text-dark'],
                            'processing' => ['Đang xử lý', 'bg-info text-dark'],
                            'completed' => ['Đã hoàn thành', 'bg-success'],
                            'cancelled' => ['Đã hủy', 'bg-danger'],
                        ];
                        [$statusLabel, $statusClass] = $statusLabels[$order->status] ?? [$order->status, 'bg-secondary'];
                    @endphp
                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                </p>

                <p>
                    <strong>Phương thức thanh toán:</strong>
                    @if ($order->payment_method === 'bank_transfer')
                        Chuyển khoản QR (mô phỏng)
                    @else
                        Thanh toán khi nhận hàng (COD)
                    @endif
                </p>

                @if ($order->payment_method === 'bank_transfer')
                    <p>
                        <strong>Mã tham chiếu mô phỏng:</strong>
                        <code>{{ \App\Support\SimulatedQr::reference($order->checkout_token, $order->id) }}</code>
                    </p>
                    <p>
                        <strong>Trạng thái thanh toán mô phỏng:</strong>
                        @if ($order->status === 'cancelled')
                            <span class="badge bg-secondary text-wrap text-start lh-sm">Đơn đã hủy — Giao dịch mô phỏng (không thu tiền thật)</span>
                        @else
                            <span class="badge bg-info text-dark text-wrap text-start lh-sm">Đã ghi nhận xác nhận mô phỏng — Chưa thu tiền thật</span>
                        @endif
                    </p>
                @else
                    <p>
                        <strong>Trạng thái thanh toán:</strong>
                        Thanh toán khi nhận hàng
                    </p>
                @endif

                <p class="fs-5">
                    <strong>Tổng tiền:</strong>
                    <span class="text-danger fw-bold">
                        {{ \App\Support\Money::format($order->total_amount) }} đ
                    </span>
                </p>
            </div>

            @if ($order->payment_method === 'bank_transfer' && !in_array($order->status, ['cancelled', 'completed'], true))
                <div class="alert alert-info text-start mt-4">
                    <strong>Thông tin thanh toán QR mô phỏng</strong>
                    <p class="mb-0 mt-2">
                        Thao tác xác nhận QR chỉ mang tính mô phỏng phục vụ đồ án,
                        không sử dụng tài khoản ngân hàng thật và chưa thu tiền thật.
                        Đơn hàng vẫn đang ở trạng thái <strong>Chờ xử lý</strong> để quản trị viên kiểm tra.
                    </p>
                </div>
            @endif

            <div class="table-responsive my-4 text-start">
                <table class="table"><caption>Sản phẩm trong đơn #{{ $order->id }}</caption>
                    <thead><tr><th scope="col">Sản phẩm</th><th scope="col">Số lượng</th><th scope="col">Thành tiền</th></tr></thead>
                    <tbody>@foreach($order->items as $item)
                        <tr><td>{{ $item->product_name }}</td><td>{{ $item->quantity }}</td><td>{{ \App\Support\Money::format($item->subtotal) }} đ</td></tr>
                    @endforeach</tbody>
                </table>
            </div>
            <a href="{{ route('store.index') }}" class="btn btn-primary mt-3">
                Tiếp tục mua sắm
            </a>
        </div>
    </div>
</div>
@endsection
