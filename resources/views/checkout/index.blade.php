@extends('layouts.app')

@section('title', 'Thanh toán')


@section('content')
<div class="container py-4">
    <h2 class="mb-4">Thông tin thanh toán</h2>

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif


@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
    <div class="row">
        {{-- Thông tin người nhận --}}
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    Thông tin người nhận
                </div>

                <div class="card-body">
                    <form action="{{ route('checkout.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">
                                Họ và tên
                            </label>
                            <input type="text"
                                   name="customer_name"
                                   class="form-control"
                                   placeholder="Nhập họ và tên">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Số điện thoại
                            </label>
                            <input type="text"
                                   name="phone"
                                   class="form-control"
                                   placeholder="Nhập số điện thoại">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Địa chỉ nhận hàng
                            </label>
                            <textarea name="address"
                                      class="form-control"
                                      rows="3"
                                      placeholder="Nhập địa chỉ nhận hàng"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Ghi chú
                            </label>
                            <textarea name="note"
                                      class="form-control"
                                      rows="2"
                                      placeholder="Ghi chú (nếu có)"></textarea>
                        </div>

                        <button type="submit" class="btn btn-success">
                             Đặt hàng
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Tóm tắt đơn hàng --}}
        <div class="col-md-5 mt-4 mt-md-0">
            <div class="card">
                <div class="card-header">
                    Đơn hàng của bạn
                </div>

                <div class="card-body">
                    @foreach ($cart as $item)
                        <div class="d-flex justify-content-between
                                    align-items-start mb-3">
                            <div>
                                <strong>{{ $item['name'] }}</strong>
                                <div class="text-muted">
                                    Số lượng: {{ $item['quantity'] }}
                                </div>
                            </div>

                            <span>
                                {{ number_format(
                                    $item['price'] * $item['quantity'],
                                    0, ',', '.'
                                ) }} đ
                            </span>
                        </div>
                    @endforeach

                    <hr>

                    <div class="d-flex justify-content-between">
                        <strong>Tổng cộng</strong>
                        <strong class="text-danger">
                            {{ number_format($total, 0, ',', '.') }} đ
                        </strong>
                    </div>

                    <a href="{{ route('cart.index') }}"
                       class="btn btn-outline-secondary mt-3">
                        Quay lại giỏ hàng
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection