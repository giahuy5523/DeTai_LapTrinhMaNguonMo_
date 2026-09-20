@extends('layouts.app')

@section('title', 'Giỏ hàng')

@section('content')
<div class="container">
    <h2 class="mb-4">Giỏ hàng của bạn</h2>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if (count($cart) > 0)
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Đơn giá</th>
                        <th>Số lượng</th>
                        <th>Thành tiền</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($cart as $id => $item)
                        <tr>
                            <td>
                                {{ $item['name'] }}
                            </td>

                            <td>
                                {{ number_format($item['price'], 0, ',', '.') }} đ
                            </td>

                            <td>
                                <form action="{{ route('cart.update', $id) }}"
                                      method="POST"
                                      class="d-flex gap-2">
                                    @csrf
                                    @method('PATCH')

                                    <input type="number"
                                           name="quantity"
                                           value="{{ $item['quantity'] }}"
                                           min="1"
                                           class="form-control"
                                           style="width: 90px">

                                    <button class="btn btn-primary btn-sm">
                                        Cập nhật
                                    </button>
                                </form>
                            </td>

                            <td>
                                {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }} đ
                            </td>

                            <td>
                                <form action="{{ route('cart.remove', $id) }}"
                                      method="POST">
                                    @csrf
                                    @method('DELETE')

                                    <button class="btn btn-danger btn-sm"
                                            onclick="return confirm('Xóa sản phẩm này?')">
                                        Xóa
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="text-end">
            <h4>
                Tổng cộng:
                <span class="text-danger">
                    {{ number_format($total, 0, ',', '.') }} đ
                </span>
            </h4>

            <a href="{{ route('checkout.index') }}"class="btn btn-success mt-2">
                Tiến hành thanh toán
            </a>
        </div>
    @else
        <div class="alert alert-info">
            Giỏ hàng đang trống.
        </div>
    @endif
</div>
@endsection