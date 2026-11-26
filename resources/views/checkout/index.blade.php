
@extends('layouts.app')

@section('title', 'Thanh toán')

@section('content')
@php
    $selectedPaymentMethod = \App\Support\FormInput::old(
        'payment_method',
        request()->query('payment_method') === 'bank_transfer' ? 'bank_transfer' : 'cod'
    );
    $isQrSelected = $selectedPaymentMethod === 'bank_transfer';
    $hasDiscount = isset($appliedVoucher) && is_array($appliedVoucher);
@endphp
    <div id="reservationAlert" class="alert alert-info mb-4" role="status">
        <div id="reservationActiveNotice">
            Hàng được giữ tối đa 5 phút theo giờ máy chủ (đến <strong>{{ $reservationExpiresLabel }}</strong>, còn <strong id="reservationCountdown"></strong>).
        </div>
        <div id="reservationExpired" class="d-none flex-column flex-sm-row align-items-sm-center justify-content-between gap-3" hidden>
            <div>
                <strong class="d-block">Đã hết thời gian giữ hàng 5 phút!</strong>
                <span>Phiên giữ hàng đã hết hạn theo giờ máy chủ và hệ thống đã giải phóng hàng giữ. Vui lòng quay về giỏ hàng để kiểm tra tồn kho và tạo lượt thanh toán mới.</span>
            </div>
            <a id="expiredBackToCartBtn" href="{{ route('cart.index') }}" class="btn btn-danger text-nowrap">
                Quay về giỏ hàng
            </a>
        </div>
    </div>
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const deadline = Date.parse(@json($reservationExpiresAt));
        const serverNow = Date.parse(@json($serverNow));
        const started = performance.now();
        const reservationAlert = document.getElementById('reservationAlert');
        const activeNotice = document.getElementById('reservationActiveNotice');
        const expiredNotice = document.getElementById('reservationExpired');
        const timer = document.getElementById('reservationCountdown');
        const qrTimer = document.getElementById('qrCountdown');
        const qrPanel = document.getElementById('qrSimulationPanel');
        const qrSvgWrapper = document.getElementById('qrSvgWrapper');
        const qrState = document.getElementById('qrSimulationState');
        const qrExpiredNotice = document.getElementById('qrExpiredNotice');
        const summaryExpiredNotice = document.getElementById('summaryExpiredNotice');
        const submitBtn = document.getElementById('checkoutSubmitBtn');
        const backToCartBtn = document.getElementById('checkoutBackToCartBtn');
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        const voucherPaymentInputs = document.querySelectorAll('.voucher-payment-method-input');
        let isExpired = false;

        function syncPaymentMethodUi() {
            const selected = document.querySelector('input[name="payment_method"]:checked');
            const method = selected ? selected.value : 'cod';
            const isQr = method === 'bank_transfer';
            if (qrPanel) {
                qrPanel.hidden = !isQr;
            }
            if (submitBtn && !isExpired) {
                submitBtn.textContent = isQr ? submitBtn.dataset.qrLabel : submitBtn.dataset.codLabel;
            }
            voucherPaymentInputs.forEach(function (input) {
                input.value = method;
            });
        }

        paymentRadios.forEach(function (radio) {
            radio.addEventListener('change', syncPaymentMethodUi);
        });
        syncPaymentMethodUi();

        function markExpired() {
            isExpired = true;
            if (reservationAlert) {
                reservationAlert.classList.remove('alert-info');
                reservationAlert.classList.add('alert-danger');
                reservationAlert.setAttribute('role', 'alert');
            }
            if (activeNotice) {
                activeNotice.hidden = true;
            }
            if (expiredNotice) {
                expiredNotice.hidden = false;
                expiredNotice.classList.remove('d-none');
                expiredNotice.classList.add('d-flex');
            }
            if (qrSvgWrapper) {
                qrSvgWrapper.classList.add('qr-demo-expired');
            }
            if (qrState) {
                qrState.textContent = 'Đã hết hạn giữ hàng — Đã giải phóng hàng giữ';
                qrState.className = 'badge bg-danger text-wrap text-start lh-sm';
            }
            if (qrExpiredNotice) {
                qrExpiredNotice.hidden = false;
            }
            if (summaryExpiredNotice) {
                summaryExpiredNotice.hidden = false;
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Phiên giữ hàng đã hết hạn';
            }
            if (backToCartBtn) {
                backToCartBtn.classList.remove('btn-outline-secondary');
                backToCartBtn.classList.add('btn-danger');
            }
            document.querySelectorAll('form[action$="/checkout"] button[type="submit"], form[action$="/checkout/voucher"] button[type="submit"], form[action$="/checkout/voucher"] input[name="voucher_code"]')
                .forEach(function (el) { el.disabled = true; });
        }

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
                markExpired();
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
        <div class="alert alert-danger" role="alert">
            <strong>Vui lòng kiểm tra lại:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Mã giảm giá (Voucher)</span>
            @if ($hasDiscount)
                <span class="badge bg-success">Đang áp dụng: {{ $appliedVoucher['code'] }} (-{{ \App\Support\Money::format($discountAmount) }} đ)</span>
            @endif
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-start">
                <div class="col-md">
                    <form action="{{ route('checkout.voucher.apply') }}" method="POST" class="d-flex flex-column flex-sm-row gap-2">
                        @csrf
                        <input type="hidden" name="payment_method" value="{{ $selectedPaymentMethod }}" class="voucher-payment-method-input">
                        <div class="flex-grow-1">
                            <label for="voucher_code" class="visually-hidden">Nhập mã voucher</label>
                            <input
                                id="voucher_code"
                                name="voucher_code"
                                type="text"
                                class="form-control @error('voucher_code') is-invalid @enderror"
                                placeholder="Nhập mã giảm giá (VD: DEMO10, DEMO50K)..."
                                value="{{ \App\Support\FormInput::old('voucher_code', $appliedVoucher['code'] ?? '') }}"
                                maxlength="32"
                                autocomplete="off"
                            >
                            @error('voucher_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-outline-dark text-nowrap align-self-start">
                            Áp dụng mã
                        </button>
                    </form>
                </div>
                @if ($hasDiscount)
                    <div class="col-md-auto">
                        <form action="{{ route('checkout.voucher.remove') }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="payment_method" value="{{ $selectedPaymentMethod }}" class="voucher-payment-method-input">
                            <button type="submit" class="btn btn-outline-danger text-nowrap w-100">
                                Gỡ voucher
                            </button>
                        </form>
                    </div>
                @endif
            </div>
            <p class="text-muted small mb-0 mt-2">
                Xem trước giảm giá không trừ lượt dùng. Hệ thống chỉ trừ lượt voucher khi đơn hàng được tạo thành công.
            </p>
        </div>
    </div>

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
                                    Mã QR chuẩn ISO/IEC 18004 quét được bằng điện thoại để xem nội dung thanh toán demo.
                                    Không dùng tài khoản nhận tiền thật.
                                </span>
                            </span>
                        </label>

                        <div id="qrSimulationPanel" class="qr-demo-panel border rounded p-3 mt-3" @if(! $isQrSelected) hidden @endif>
                            <span class="badge bg-warning text-dark text-wrap text-start lh-sm mb-3">MÔ PHỎNG HỌC TẬP — KHÔNG CHUYỂN TIỀN THẬT</span>
                            <div class="row g-3 align-items-center">
                                <div id="qrSvgWrapper" class="col-sm-auto text-center">
                                    {!! $qrSvg !!}
                                    <div class="small text-muted mt-1">Quét bằng camera điện thoại</div>
                                </div>
                                <div class="col-sm">
                                    <p class="mb-2">
                                        <strong>Số tiền mô phỏng:</strong>
                                        <strong class="text-danger">{{ \App\Support\Money::format($total) }} đ</strong>
                                    </p>
                                    @if ($hasDiscount)
                                        <p class="mb-2 small text-success">
                                            <strong>Đã áp dụng voucher:</strong>
                                            <code>{{ $appliedVoucher['code'] }}</code>
                                            (giảm {{ \App\Support\Money::format($discountAmount) }} đ từ {{ \App\Support\Money::format($subtotal) }} đ)
                                        </p>
                                    @endif
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
                                    <p class="mb-2 text-muted small">
                                        Dùng điện thoại quét mã QR sẽ hiện nội dung thanh toán demo (không dùng tài khoản ngân hàng thật).
                                        Bấm xác nhận chỉ tạo đơn chờ xử lý, không đồng nghĩa đã thu tiền thật.
                                    </p>
                                    <details class="small">
                                        <summary class="text-muted">Xem nội dung mã hóa trong QR</summary>
                                        <pre id="qrPayloadPreview" class="qr-payload-preview bg-white border rounded p-2 mt-2 mb-0 small">{{ $qrPayload }}</pre>
                                    </details>
                                </div>
                            </div>
                            <div id="qrExpiredNotice" class="alert alert-danger mt-3 mb-0" role="alert" hidden>
                                <strong>Mã QR mô phỏng đã hết hạn giữ hàng (5 phút).</strong>
                                <p class="mb-2 mt-1 small">Hệ thống đã giải phóng hàng giữ cho các khách hàng khác. Vui lòng quay về giỏ hàng để đặt lại nếu còn hàng.</p>
                                <a href="{{ route('cart.index') }}" class="btn btn-danger btn-sm">Quay về giỏ hàng</a>
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

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Tạm tính</span>
                            <span>{{ \App\Support\Money::format($subtotal) }} đ</span>
                        </div>

                        @if ($hasDiscount)
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>Giảm giá (<code>{{ $appliedVoucher['code'] }}</code>)</span>
                                <span>−{{ \App\Support\Money::format($discountAmount) }} đ</span>
                            </div>
                        @endif

                        <div class="d-flex justify-content-between align-items-baseline pt-2 border-top">
                            <strong>Tổng thanh toán</strong>
                            <strong class="text-danger fs-5">
                                {{ \App\Support\Money::format($total) }} đ
                            </strong>
                        </div>

                        <div id="summaryExpiredNotice" class="alert alert-danger small mt-3 mb-0" role="alert" hidden>
                            <strong>Hết thời gian giữ hàng 5 phút.</strong> Hệ thống đã giải phóng hàng giữ, vui lòng bấm <strong>Quay về giỏ hàng</strong> bên dưới để thử lại.
                        </div>

                        <button
                            id="checkoutSubmitBtn"
                            type="submit"
                            class="btn btn-success w-100 mt-4"
                            data-cod-label="Xác nhận đặt hàng"
                            data-qr-label="Xác nhận thanh toán mô phỏng"
                        >{{ $isQrSelected ? 'Xác nhận thanh toán mô phỏng' : 'Xác nhận đặt hàng' }}</button>

                        <a
                            id="checkoutBackToCartBtn"
                            href="{{ route('cart.index') }}"
                            class="btn btn-outline-secondary w-100 mt-2"
                        >
                            Quay về giỏ hàng
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
