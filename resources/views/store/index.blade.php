@extends('layouts.app')

@section('title', 'Sản phẩm')

@section('content')
<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="mb-0">Sản phẩm</h2>
        @if($keyword !== '')
            <span class="text-muted small">Tìm thấy {{ $products->total() }} sản phẩm phù hợp</span>
        @endif
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('store.index') }}" method="GET" role="search" class="row g-2 mb-4">
        <div class="col">
            <label for="store-keyword" class="visually-hidden">Tên sản phẩm cần tìm</label>
            <input
                type="search"
                id="store-keyword"
                name="keyword"
                value="{{ $keyword }}"
                class="form-control"
                placeholder="Nhập tên giày bạn muốn tìm..."
                maxlength="255"
                autocomplete="off"
            >
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
        </div>
        @if($keyword !== '')
            <div class="col-auto">
                <a href="{{ route('store.index') }}" class="btn btn-outline-secondary">Xóa tìm kiếm</a>
            </div>
        @endif
    </form>

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
                            {{ \App\Support\Money::format($product->price) }} đ
                        </p>

                        <p class="text-muted">
                            Còn {{ $product->quantity }} sản phẩm
                        </p>

                        @if(auth()->check() && auth()->user()->role === 'admin')
                            <a class="btn btn-outline-dark mt-auto" href="{{ route('admin.products.edit', $product) }}">Quản lý sản phẩm</a>
                        @else
                        <form
                            action="{{ route('cart.add', $product->id) }}"
                            method="POST"
                            class="mt-auto"
                        >
                            @csrf

                            <input
                                type="number"
                                name="quantity"
                                aria-label="Số lượng {{ $product->name }}"
                                required
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
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info" role="status">
                    @if($keyword !== '')
                        Không tìm thấy sản phẩm phù hợp với từ khóa "{{ $keyword }}".
                        <a href="{{ route('store.index') }}" class="alert-link">Xem tất cả sản phẩm</a>.
                    @else
                        Chưa có sản phẩm nào đang bán.
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    @if($products->hasPages())
        <div class="mt-4">
            {{ $products->links() }}
        </div>
    @endif
</div>
@endsection
