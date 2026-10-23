@extends('layouts.app')
@section('title', 'Trang chủ - SneakerShop')
@section('content')
<section class="shop-hero p-4 p-lg-5 mb-5">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="badge text-bg-light mb-3">SNEAKERS · THỂ THAO · PHONG CÁCH</span>
            <h1 class="display-4 fw-bold mb-3">Bước đi mới.<br>Phong cách của bạn.</h1>
            <p class="lead mb-4">Khám phá bộ sưu tập giày thể thao tại SneakerShop và chọn đôi giày cho mỗi ngày năng động.</p>
            <a class="btn btn-light btn-lg" href="{{ route('store.index') }}">Khám phá sản phẩm →</a>
        </div>
        <div class="col-lg-4">
            <div class="hero-note p-4">
                <span class="display-3 fw-bold">01</span>
                <p class="h4 mt-3">Sẵn sàng cho<br>hành trình mới</p>
                <p class="mb-0 small">Xem sản phẩm · Thêm vào giỏ · Đặt hàng</p>
            </div>
        </div>
    </div>
</section>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><p class="text-uppercase text-muted small mb-1">Bộ sưu tập</p><h2 class="fw-bold mb-0">Sản phẩm mới nhất</h2></div>
    <a class="btn btn-outline-dark" href="{{ route('store.index') }}">Xem tất cả</a>
</div>
<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
    @forelse($newProducts as $product)
        <div class="col">
            <article class="card product-card h-100">
                @if($product->image)
                    <img src="{{ asset('storage/' . $product->image) }}" class="card-img-top product-image" alt="{{ $product->name }}" loading="lazy">
                @else
                    <div class="product-image d-flex align-items-center justify-content-center text-muted">Chưa có ảnh sản phẩm</div>
                @endif
                <div class="card-body d-flex flex-column">
                    <p class="small text-muted mb-1">{{ $product->category->name ?? 'Khác' }}</p>
                    <h3 class="h5">{{ $product->name }}</h3>
                    <p class="fw-bold fs-5">{{ \App\Support\Money::format($product->price) }} đ</p>
                    @if($product->quantity < 1)
                        <button class="btn btn-secondary mt-auto" disabled>Hết hàng</button>
                    @elseif(auth()->check() && auth()->user()->role !== 'customer')
                        <a class="btn btn-outline-dark mt-auto" href="{{ route('admin.products.edit', $product) }}">Quản lý sản phẩm</a>
                    @else
                        <form action="{{ route('cart.add', $product->id) }}" method="POST" class="mt-auto">
                            @csrf
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn btn-dark w-100" type="submit">Thêm vào giỏ</button>
                        </form>
                    @endif
                </div>
            </article>
        </div>
    @empty
        <div class="col-12 w-100">
            <div class="empty-state text-center p-5">
                <h3 class="h5">Bộ sưu tập đang được cập nhật</h3>
                <p class="text-muted mb-0">Hiện chưa có sản phẩm đang bán. Hãy quay lại sau nhé.</p>
            </div>
        </div>
    @endforelse
</div>
@endsection
