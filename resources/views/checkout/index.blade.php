
@extends('layouts.app')

@section('title', 'Thanh toán')

@section('content')
@php
    $selectedPaymentMethod = \App\Support\FormInput::old(
        'payment_method',
        request()->query('payment_method') === 'bank_transfer' ? 'bank_transfer' : 'cod'
    );
    $isQrSelected = $selectedPaymentMethod === 'bank_transfer';
@endphp
    <div class="alert alert-info" role="status">
        Hàng được giữ tối đa 5 phút theo giờ máy chủ (đến <strong>{{ $reservationExpiresLabel }}</strong>, còn <strong id="reservationCountdown"></strong>).
        <div id="reservationExpired" class="mt-1 fw-semibold text-danger" hidden>Lượt giữ hàng đã hết hạn theo giờ máy chủ. Vui lòng quay lại giỏ hàng để tạo lượt giữ mới nếu còn hàng.</div>
    </div>
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const deadline = Date.parse(@json($reservationExpiresAt));
        const serverNow = Date.parse(@json($serverNow));
        const started = performance.now();
        const timer = document.getElementById('reservationCountdown');
        const qrTimer = document.getElementById('qrCountdown');
        const qrPanel = document.getElementById('qrSimulationPanel');
        const qrState = document.getElementById('qrSimulationState');
        const submitBtn = document.getElementById('checkoutSubmitBtn');
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');

        function syncPaymentMethodUi() {
            const selected = document.querySelector('input[name="payment_method"]:checked');
            const isQr = selected && selected.value === 'bank_transfer';
            if (qrPanel) {
                qrPanel.hidden = !isQr;
            }
            if (submitBtn) {
                submitBtn.textContent = isQr ? submitBtn.dataset.qrLabel : submitBtn.dataset.codLabel;
            }
        }

        paymentRadios.forEach(function (radio) {
            radio.addEventListener('change', syncPaymentMethodUi);
        });
        syncPaymentMethodUi();

        function tick() {
            const seconds = Math.max(0, Math.ceil((deadline - serverNow - (performance.now() - started)) / 1000));
            const formatted = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
            if (timer) {
                timer.textContent = formatted;
            }
            if (qrTimer) {
                qrTimer.textContent = formatted;
            }
            if (seconds === 0) {
                const expiredNotice = document.getElementById('reservationExpired');
                if (expiredNotice) {
                    expiredNotice.hidden = false;
                }
                if (qrState) {
                    qrState.textContent = 'Đã hết hạn giữ hàng';
                    qrState.className = 'badge bg-danger';
                }
                document.querySelectorAll('form[action$="/checkout"] button[type="submit"]').forEach(button => button.disabled = true);
                clearInterval(interval);
            }
        }
        const interval = setInterval(tick, 1000);
        tick();
    });
    </script>
    @endpush
<div class="container py-4">
    <h2 class="mb-4">Thanh toán đơn hàng</h2>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Vui lòng kiểm tra lại:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('checkout.store') }}" method="POST">
        @csrf
        <input type="hidden" name="checkout_version" value="{{ $checkoutVersion }}">

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header fw-bold">
                        1. Thông tin người nhận
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">
                                Họ và tên
                            </label>
                            <input
                                id="customer_name"
                                name="customer_name"
                                type="text"
                                class="form-control @error('customer_name') is-invalid @enderror"
                                value="{{ \App\Support\FormInput::old('customer_name') }}"
                                maxlength="255"
                                autocomplete="name"
                                required
                            >
                            @error('customer_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">
                                Số điện thoại
                            </label>
                            <input
                                id="phone"
                                name="phone"
                                type="tel"
                                class="form-control @error('phone') is-invalid @enderror"
                                value="{{ \App\Support\FormInput::old('phone') }}"
                                maxlength="20"
                                autocomplete="tel"
                                required
                            >
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">
                                Địa chỉ nhận hàng
                            </label>
                            <textarea
                                id="address"
                                name="address"
                                class="form-control @error('address') is-invalid @enderror"
                                rows="3"
                                autocomplete="street-address"
                                maxlength="1000"
                                required
                            >{{ \App\Support\FormInput::old('address') }}</textarea>
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-0">
                            <label for="note" class="form-label">
                                Ghi chú (không bắt buộc)
                            </label>
                            <textarea
                                id="note"
                                name="note"
                                class="form-control @error('note') is-invalid @enderror"
                                rows="2"
                                maxlength="2000"
                            >{{ \App\Support\FormInput::old('note') }}</textarea>
                            @error('note')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header fw-bold">
                        2. Phương thức thanh toán
                    </div>

                    <div class="card-body">
                        <label class="border rounded p-3 d-flex gap-3 mb-3" for="paymentMethodCod">
                            <input
                                id="paymentMethodCod"
                                class="form-check-input mt-1"
                                type="radio"
                                name="payment_method"
                                value="cod"
                                @checked(! $isQrSelected)
                                required
                            >
                            <span>
                                <strong>Thanh toán khi nhận hàng (COD)</strong>
                                <span class="d-block text-muted small">
                                    Tạo đơn và trừ tồn kho ngay khi đặt; đơn COD chờ xử lý không tự hủy sau 5 phút.
                                </span>
                            </span>
                        </label>

                        <label class="border rounded p-3 d-flex gap-3" for="paymentMethodQr">
                            <input
                                id="paymentMethodQr"
                                class="form-check-input mt-1"
                                type="radio"
                                name="payment_method"
                                value="bank_transfer"
                                @checked($isQrSelected)
                                required
                            >
                            <span>
                                <strong>Chuyển khoản QR mô phỏng (giữ hàng 5 phút)</strong>
                                <span class="d-block text-muted small">
                                    Hiển thị mã QR mô phỏng, số tiền, mã tham chiếu và thời hạn giữ hàng trên máy chủ.
                                    Không dùng tài khoản nhận tiền thật.
                                </span>
                            </span>
                        </label>

                        <div id="qrSimulationPanel" class="qr-demo-panel border rounded p-3 mt-3" @if(! $isQrSelected) hidden @endif>
                            <span class="badge bg-warning text-dark text-wrap text-start lh-sm mb-3">MÔ PHỎNG HỌC TẬP — KHÔNG CHUYỂN TIỀN THẬT</span>
                            <div class="row g-3 align-items-center">
                                <div class="col-sm-auto text-center">
                                    {!! $qrSvg !!}
                                </div>
                                <div class="col-sm">
                                    <p class="mb-2">
                                        <strong>Số tiền mô phỏng:</strong>
                                        <strong class="text-danger">{{ \App\Support\Money::format($total) }} đ</strong>
                                    </p>
                                    <p class="mb-2">
                                        <strong>Mã tham chiếu:</strong>
                                        <code id="qrReferenceCode">{{ $qrReference }}</code>
                                    </p>
                                    <p class="mb-2">
                                        <strong>Thời hạn giữ hàng (giờ server):</strong>
                                        <span id="qrExpiresLabel">{{ $reservationExpiresLabel }}</span>
                                        <span class="text-nowrap">(còn <strong id="qrCountdown"></strong>)</span>
                                    </p>
                                    <p class="mb-2">
                                        <strong>Trạng thái:</strong>
                                        <span id="qrSimulationState" class="badge bg-info text-dark text-wrap text-start lh-sm">Chờ xác nhận thanh toán mô phỏng</span>
                                    </p>
                                    <p class="mb-0 text-muted small">
                                        Chỉ dùng để mô phỏng xác nhận trong thời hạn 5 phút trên máy chủ.
                                        Bấm xác nhận chỉ tạo đơn chờ xử lý, không đồng nghĩa đã thu tiền thật.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-0 small">
                            Đơn hàng được ghi nhận với trạng thái chờ xử lý.
                            Chọn chuyển khoản QR mô phỏng ở đây chưa có nghĩa là hệ thống
                            đã xác nhận tiền về tài khoản.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header fw-bold">
                        Đơn hàng của bạn
                    </div>

                    <div class="card-body">
                        @foreach ($cart as $item)
                            <div class="d-flex justify-content-between gap-3 mb-3">
                                <div>
                                    <strong>{{ $item['name'] }}</strong>
                                    <div class="text-muted small">
                                        Số lượng: {{ $item['quantity'] }}
                                    </div>
                                </div>

                                <span class="text-nowrap">
                                    {{ \App\Support\Money::formattedLine($item['price'], (int) $item['quantity']) }} đ
                                </span>
                            </div>
                        @endforeach

                        <hr>

                        <div class="d-flex justify-content-between">
                            <strong>Tổng thanh toán</strong>
                            <strong class="text-danger fs-5">
                                {{ \App\Support\Money::format($total) }} đ
                            </strong>
                        </div>

                        <button
                            id="checkoutSubmitBtn"
                            type="submit"
                            class="btn btn-success w-100 mt-4"
                            data-cod-label="Xác nhận đặt hàng"
                            data-qr-label="Xác nhận thanh toán mô phỏng"
                        >{{ $isQrSelected ? 'Xác nhận thanh toán mô phỏng' : 'Xác nhận đặt hàng' }}</button>

                        <a
                            href="{{ route('cart.index') }}"
                            class="btn btn-outline-secondary w-100 mt-2"
                        >
                            Quay lại giỏ hàng
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
