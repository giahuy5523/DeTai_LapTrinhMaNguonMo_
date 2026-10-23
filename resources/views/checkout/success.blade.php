
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
                    <strong>Trạng thái:</strong>
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
                        Chuyển khoản ngân hàng
                    @else
                        Thanh toán khi nhận hàng (COD)
                    @endif
                </p>

                <p class="fs-5">
                    <strong>Tổng tiền:</strong>
                    <span class="text-danger fw-bold">
                        {{ \App\Support\Money::format($order->total_amount) }} đ
                    </span>
                </p>
            </div>

            @if ($order->payment_method === 'bank_transfer' && !in_array($order->status, ['cancelled', 'completed'], true))
                <div class="alert alert-info text-start mt-4">
                    <strong>Hướng dẫn chuyển khoản</strong>
                    <p class="mb-0 mt-2">
                        Vui lòng liên hệ cửa hàng để nhận thông tin tài khoản
                        và nội dung chuyển khoản chính xác. Đơn hàng chưa được
                        xác nhận thanh toán tự động.
                    </p>
                </div>
            @endif

            <a href="{{ route('store.index') }}" class="btn btn-primary mt-3">
                Tiếp tục mua sắm
            </a>
        </div>
    </div>
</div>
@endsection
