@extends('layouts.app')

@section('title', 'Sản phẩm')

@section('content')
<div class="container">
    <h2 class="mb-4">Sản phẩm</h2>

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

    <div class="row g-4">
        @forelse ($products as $product)
            <div class="col-md-4 col-lg-3">
                <div class="card h-100">
                    @if ($product->image)
                        <img
                            src="{{ asset('storage/' . $product->image) }}"
                            class="card-img-top"
                            alt="{{ $product->name }}"
                            style="height: 200px; object-fit: contain;"
                        >
                    @endif

                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            {{ $product->name }}
                        </h5>

                        <p class="text-danger fw-bold">
                            {{ number_format($product->price, 0, ',', '.') }} đ
                        </p>

                        <p class="text-muted">
                            Còn {{ $product->quantity }} sản phẩm
                        </p>

                        <form
                            action="{{ route('cart.add', $product->id) }}"
                            method="POST"
                            class="mt-auto"
                        >
                            @csrf

                            <input
                                type="number"
                                name="quantity"
                                value="1"
                                min="1"
                                max="{{ $product->quantity }}"
                                class="form-control mb-2"
                            >

                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >
                                Thêm vào giỏ hàng
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info">
                    Chưa có sản phẩm nào đang bán.
                </div>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $products->links() }}
    </div>
</div>
@endsection