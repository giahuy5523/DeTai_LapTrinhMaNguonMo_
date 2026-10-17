
@extends('layouts.app')

@section('title', 'Giỏ hàng')

@section('content')
<div class="container py-4">
    <h2 class="mb-4">Giỏ hàng của bạn</h2>

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
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

    @if (count($cart) > 0)
        @php
            $hasUnavailableProduct = collect($cart)->contains(
                fn ($item) => empty($item['is_active'])
                    || (int) ($item['stock'] ?? 0) < (int) $item['quantity']
            );
        @endphp

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
                                <div class="d-flex align-items-center gap-3">
                                    @if (!empty($item['image']))
                                        <img
                                            src="{{ asset('storage/' . $item['image']) }}"
                                            alt="{{ $item['name'] }}"
                                            style="width:70px;height:70px;object-fit:cover"
                                            class="rounded"
                                            onerror="this.style.display='none'"
                                        >
                                    @endif

                                    <div>
                                        <strong>{{ $item['name'] }}</strong>

                                        @if (empty($item['is_active']))
                                            <div class="text-danger small">
                                                Sản phẩm đã ngừng bán
                                            </div>
                                        @elseif ((int) ($item['stock'] ?? 0) < (int) $item['quantity'])
                                            <div class="text-danger small">
                                                Kho chỉ còn {{ $item['stock'] ?? 0 }} sản phẩm
                                            </div>
                                        @else
                                            <div class="text-muted small">
                                                Còn {{ $item['stock'] }} sản phẩm
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <td class="text-nowrap">
                                {{ number_format($item['price'], 0, ',', '.') }} đ
                            </td>

                            <td>
                                <form
                                    action="{{ route('cart.update', $id) }}"
                                    method="POST"
                                    class="d-flex align-items-center gap-2"
                                >
                                    @csrf
                                    @method('PATCH')

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        onclick="changeQuantity(this, -1)"
                                        aria-label="Giảm số lượng"
                                    >−</button>

                                    <input
                                        type="number"
                                        name="quantity"
                                        value="{{ $item['quantity'] }}"
                                        min="1"
                                        max="{{ max(1, (int) ($item['stock'] ?? 0)) }}"
                                        required
                                        class="form-control text-center"
                                        style="width:75px"
                                        aria-label="Số lượng {{ $item['name'] }}"
                                    >

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        onclick="changeQuantity(this, 1)"
                                        aria-label="Tăng số lượng"
                                    >+</button>

                                    <button type="submit" class="btn btn-primary btn-sm">
                                        Cập nhật
                                    </button>
                                </form>
                            </td>

                            <td class="text-nowrap">
                                {{ number_format(
                                    $item['price'] * $item['quantity'],
                                    0, ',', '.'
                                ) }} đ
                            </td>

                            <td>
                                <form
                                    action="{{ route('cart.remove', $id) }}"
                                    method="POST"
                                    onsubmit="return confirm('Bạn có chắc muốn xóa sản phẩm này?')"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn btn-danger btn-sm">
                                        Xóa
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <a href="{{ route('store.index') }}" class="btn btn-outline-primary">
                Tiếp tục mua sắm
            </a>

            <div class="text-md-end">
                <h4>
                    Tổng cộng:
                    <span class="text-danger">
                        {{ number_format($total, 0, ',', '.') }} đ
                    </span>
                </h4>

                @if ($hasUnavailableProduct)
                    <div class="text-danger mb-2">
                        Hãy cập nhật số lượng hoặc xóa sản phẩm không khả dụng trước khi thanh toán.
                    </div>
                @else
                    <a href="{{ route('checkout.index') }}" class="btn btn-success mt-2">
                        Tiến hành thanh toán
                    </a>
                @endif
            </div>
        </div>
    @else
        <div class="alert alert-info">Giỏ hàng đang trống.</div>

        <a href="{{ route('store.index') }}" class="btn btn-primary">
            Tiếp tục mua sắm
        </a>
    @endif
</div>
    {{-- DANH SÁCH ĐƠN HÀNG ĐÃ ĐẶT --}}
    <hr class="my-5">

    <h2 class="mb-4">Đơn hàng của bạn</h2>

    @if (isset($recentOrders) && $recentOrders->isNotEmpty())
        @foreach ($recentOrders as $order)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong>Đơn hàng #{{ $order->id }}</strong>

                    <span class="badge
                        @if ($order->status === 'pending') bg-warning text-dark
                        @elseif ($order->status === 'cancelled') bg-danger
                        @elseif ($order->status === 'completed') bg-success
                        @else bg-secondary
                        @endif">
                        {{ $order->status }}
                    </span>
                </div>

                <div class="card-body">
                    <p class="mb-1">
                        <strong>Ngày đặt:</strong>
                        {{ $order->created_at->format('d/m/Y H:i') }}
                    </p>

                    <p class="mb-1">
                        <strong>Người nhận:</strong>
                        {{ $order->customer_name }}
                    </p>

                    <p class="mb-1">
                        <strong>Địa chỉ:</strong>
                        {{ $order->address }}
                    </p>

                    <p class="mb-3">
                        <strong>Tổng tiền:</strong>
                        <span class="text-danger fw-bold">
                            {{ number_format($order->total_amount, 0, ',', '.') }} đ
                        </span>
                    </p>

                    <strong>Sản phẩm:</strong>
                    <ul class="mt-2">
                        @foreach ($order->items as $item)
                            <li>
                                {{ $item->product_name }}
                                × {{ $item->quantity }}
                                — {{ number_format($item->subtotal, 0, ',', '.') }} đ
                            </li>
                        @endforeach
                    </ul>

                    @if ($order->status === 'pending')
                        <form
                            action="{{ route('orders.cancel', $order->id) }}"
                            method="POST"
                            onsubmit="return confirm('Mày có chắc muốn hủy đơn hàng này không?')"
                        >
                            @csrf
                            @method('PATCH')

                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                Hủy đơn hàng
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    @elseif (isset($recentOrders))
        <div class="alert alert-info">
            Bạn chưa có đơn hàng nào.
        </div>
    @endif
<script>
    function changeQuantity(button, amount) {
        const input = button.closest('form').querySelector('[name="quantity"]');
        const min = Number(input.min) || 1;
        const max = Number(input.max) || 999999;
        const current = Number.parseInt(input.value, 10) || min;

        input.value = Math.min(max, Math.max(min, current + amount));
    }
</script>
@endsection
