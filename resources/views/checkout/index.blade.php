
@extends('layouts.app')

@section('title', 'Thanh toán')

@section('content')
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
                        <label class="border rounded p-3 d-flex gap-3 mb-3">
                            <input
                                class="form-check-input mt-1"
                                type="radio"
                                name="payment_method"
                                value="cod"
                                @checked(\App\Support\FormInput::old('payment_method', 'cod') === 'cod')
                                required
                            >
                            <span>
                                <strong>Thanh toán khi nhận hàng (COD)</strong>
                                <span class="d-block text-muted small">
                                    Thanh toán trực tiếp khi nhận sản phẩm.
                                </span>
                            </span>
                        </label>


<label class="border rounded p-3 d-flex gap-3">
    <input
        class="form-check-input mt-1"
        type="radio"
        name="payment_method"
        value="bank_transfer"
        @checked(\App\Support\FormInput::old('payment_method', 'cod') === 'bank_transfer')
        required
    >
    <span>
        <strong>Chuyển khoản ngân hàng (demo)</strong>
        <span class="d-block text-muted small">
            Hiển thị thông tin minh họa sau khi đặt hàng.
            Không yêu cầu tài khoản ngân hàng thật.
        </span>
    </span>
</label>


                        <div class="alert alert-info mt-3 mb-0 small">
                            Đơn hàng được ghi nhận với trạng thái chờ xử lý.
                            Chọn chuyển khoản ở đây chưa có nghĩa là hệ thống
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

                        <button type="submit" class="btn btn-success w-100 mt-4">
                            Xác nhận đặt hàng
                        </button>

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
