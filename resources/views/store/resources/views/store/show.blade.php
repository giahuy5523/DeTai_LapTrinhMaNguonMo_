@extends('layouts.app')

@section('title', $product->name . ' - SneakerShop')

@section('content')
<div class="container">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="{{ route('store.index') }}">Sản phẩm</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="row g-4 g-lg-5 align-items-start">
        <div class="col-12 col-lg-6">
            <div class="card border-0 overflow-hidden">
                @if($product->image)
                    <img
                        src="{{ asset('storage/' . $product->image) }}"
                        class="img-fluid w-100 p-3"
                        alt="{{ $product->name }}"
                        style="max-height: 520px; object-fit: contain;"
                    >
                @else
                    <div class="d-flex align-items-center justify-content-center bg-light text-muted" style="min-height: 360px;">Chưa có ảnh sản phẩm</div>
                @endif
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <p class="text-uppercase small text-muted mb-2">{{ $product->category->name ?? 'Giày thể thao' }}</p>
            <h1 class="display-6 fw-bold mb-3">{{ $product->name }}</h1>
            <p class="display-6 fw-bold text-danger mb-3">{{ \App\Support\Money::format($product->price) }} đ</p>

            @if($product->quantity > 0)
                <p class="text-success fw-semibold"><span aria-hidden="true">●</span> Còn hàng — {{ $product->quantity }} sản phẩm</p>
            @else
                <p class="text-danger fw-semibold"><span aria-hidden="true">●</span> Hiện đã hết hàng</p>
            @endif

            <div class="border-top border-bottom py-4 my-4">
                <h2 class="h5 fw-bold">Mô tả sản phẩm</h2>
                @if($product->description)
                    <p class="mb-0" style="white-space: pre-line;">{{ $product->description }}</p>
                @else
                    <p class="text-muted mb-0">Thông tin mô tả cho sản phẩm đang được cập nhật.</p>
                @endif
            </div>

            @if(auth()->check() && auth()->user()->role === 'admin')
                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-dark btn-lg">Quản lý sản phẩm</a>
            @elseif($product->quantity > 0)
                <form action="{{ route('cart.add', $product->id) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-4 col-sm-3">
                        <label for="detail-quantity" class="form-label fw-semibold">Số lượng</label>
                        <input
                            type="number"
                            id="detail-quantity"
                            name="quantity"
                            class="form-control form-control-lg"
                            value="1"
                            min="1"
                            max="{{ $product->quantity }}"
                            required
                        >
                    </div>
                    <div class="col-8 col-sm-9 d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Thêm vào giỏ hàng</button>
                    </div>
                </form>
            @else
                <button type="button" class="btn btn-secondary btn-lg" disabled>Sản phẩm tạm hết hàng</button>
            @endif

            <a href="{{ route('store.index') }}" class="btn btn-link text-decoration-none px-0 mt-3">← Quay lại cửa hàng</a>
        </div>
    </div>

    @if($relatedProducts->isNotEmpty())
        <section class="mt-5 pt-4 border-top">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h3 fw-bold mb-0">Sản phẩm cùng danh mục</h2>
                <a href="{{ route('store.index', ['category_id' => $product->category_id]) }}" class="text-decoration-none">Xem thêm</a>
            </div>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
                @foreach($relatedProducts as $related)
                    <div class="col">
                        <article class="card product-card h-100">
                            <a href="{{ route('store.show', ['product' => $related->slug]) }}" class="text-decoration-none">
                                @if($related->image)
                                    <img src="{{ asset('storage/' . $related->image) }}" class="card-img-top product-image" alt="{{ $related->name }}" loading="lazy">
                                @else
                                    <div class="product-image d-flex align-items-center justify-content-center text-muted">Chưa có ảnh sản phẩm</div>
                                @endif
                            </a>
                            <div class="card-body">
                                <h3 class="h6"><a href="{{ route('store.show', ['product' => $related->slug]) }}" class="text-dark text-decoration-none">{{ $related->name }}</a></h3>
                                <p class="fw-bold text-danger mb-0">{{ \App\Support\Money::format($related->price) }} đ</p>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
