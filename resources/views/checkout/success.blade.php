@extends('layouts.app')

@section('title', 'Đặt hàng thành công')

@section('content')
<div class="container py-5">
    <div class="card text-center mx-auto" style="max-width: 600px;">
        <div class="card-body py-5">
            <h2 class="text-success mb-3">
                Đặt hàng thành công!
            </h2>

            <p>
                Cảm ơn bạn đã mua hàng.
            </p>

            @if ($orderId)
                <p>
                    Mã đơn hàng:
                    <strong>#{{ $orderId }}</strong>
                </p>
            @endif

            <a href="{{ route('store.index') }}"
               class="btn btn-primary mt-3">
                Tiếp tục mua sắm
            </a>
        </div>
    </div>
</div>
@endsection