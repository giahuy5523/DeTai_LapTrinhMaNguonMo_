@extends('layouts.app')
@section('title', 'Trang chủ - SneakerShop')
@section('content')
<section class="shop-hero mb-5 overflow-hidden">
    <div class="row g-0 align-items-stretch">
        <div class="col-lg-5 p-4 p-lg-5 d-flex flex-column justify-content-center">
            <h1 class="display-4 fw-bold mb-3">Bước đi mới.<br>Phong cách của bạn.</h1>
            <p class="lead">Khám phá giày thể thao cho mỗi ngày năng động.</p>
            <a class="btn btn-light btn-lg align-self-start" href="{{ route('store.index') }}">Khám phá sản phẩm →</a>
        </div>
        <div class="col-lg-7">
            <div id="shoeCarousel" class="carousel slide" aria-label="Bộ sưu tập giày">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#shoeCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Ảnh giày 1"></button>
                    <button type="button" data-bs-target="#shoeCarousel" data-bs-slide-to="1" aria-label="Ảnh giày 2"></button>
                </div>
                <div class="carousel-inner">
                    <div class="carousel-item active"><img src="{{ asset('images/hero/red-sneaker.jpg') }}" class="d-block w-100 hero-photo" alt="Giày thể thao đỏ trên nền đỏ" fetchpriority="high"></div>
                    <div class="carousel-item"><img src="{{ asset('images/hero/everyday-sneaker.jpg') }}" class="d-block w-100 hero-photo" alt="Giày thể thao phong cách hằng ngày" loading="lazy"></div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#shoeCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Ảnh trước</span></button>
                <button class="carousel-control-next" type="button" data-bs-target="#shoeCarousel" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Ảnh tiếp theo</span></button>
            </div>
            <div class="text-end p-2"><button id="heroPlayback" class="btn btn-outline-light btn-sm" type="button" aria-pressed="false">Tạm dừng chuyển ảnh</button></div>
        </div>
    </div>
</section>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const carousel = new bootstrap.Carousel(document.getElementById('shoeCarousel'), { interval: 6000, ride: false, pause: 'hover' });
    const button = document.getElementById('heroPlayback');
    let paused = reduced;
    function updatePlayback() {
        if (paused) carousel.pause(); else carousel.cycle();
        button.textContent = paused ? 'Tiếp tục chuyển ảnh' : 'Tạm dừng chuyển ảnh';
        button.setAttribute('aria-pressed', String(paused));
    }
    button.addEventListener('click', function () { paused = !paused; updatePlayback(); });
    updatePlayback();
});
</script>
@endpush
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
