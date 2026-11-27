@extends('layouts.app')

@section('title', 'Sản phẩm - SneakerShop')

@section('content')
<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
        <div>
            <p class="text-uppercase text-muted small mb-1">Bộ sưu tập SneakerShop</p>
            <h1 class="h2 fw-bold mb-0">Tất cả sản phẩm</h1>
        </div>
        <span class="text-muted small">{{ number_format($products->total()) }} sản phẩm phù hợp</span>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 mb-4">
        <div class="card-body">
            <form action="{{ route('store.index') }}" method="GET" role="search">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label for="store-keyword" class="form-label fw-semibold">Tìm theo tên sản phẩm</label>
                        <input
                            type="search"
                            id="store-keyword"
                            name="keyword"
                            value="{{ $keyword }}"
                            class="form-control"
                            placeholder="Ví dụ: Nike Air Max..."
                            maxlength="255"
                            autocomplete="off"
                        >
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label for="store-category" class="form-label fw-semibold">Danh mục</label>
                        <select id="store-category" name="category_id" class="form-select">
                            <option value="">Tất cả danh mục</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-sm-3 col-lg-2">
                        <label for="store-min-price" class="form-label fw-semibold">Giá từ (đ)</label>
                        <input
                            type="number"
                            id="store-min-price"
                            name="min_price"
                            value="{{ $minPrice }}"
                            class="form-control"
                            min="0"
                            step="1000"
                            placeholder="Tối thiểu"
                        >
                    </div>
                    <div class="col-6 col-sm-3 col-lg-2">
                        <label for="store-max-price" class="form-label fw-semibold">Đến (đ)</label>
                        <input
                            type="number"
                            id="store-max-price"
                            name="max_price"
                            value="{{ $maxPrice }}"
                            class="form-control"
                            min="0"
                            step="1000"
                            placeholder="Tối đa"
                        >
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label for="store-sort" class="form-label fw-semibold">Sắp xếp</label>
                        <select id="store-sort" name="sort" class="form-select">
                            <option value="newest" @selected($sort === 'newest')>Mới nhất</option>
                            <option value="price_asc" @selected($sort === 'price_asc')>Giá thấp đến cao</option>
                            <option value="price_desc" @selected($sort === 'price_desc')>Giá cao đến thấp</option>
                            <option value="name_asc" @selected($sort === 'name_asc')>Tên A–Z</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 d-grid">
                        <button type="submit" class="btn btn-primary">Áp dụng bộ lọc</button>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 d-grid">
                        <a href="{{ route('store.index') }}" class="btn btn-outline-secondary">Xóa bộ lọc</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($keyword !== '' || $categoryId || $minPrice !== null || $maxPrice !== null || $sort !== 'newest')
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="small text-muted">Đang áp dụng bộ lọc:</span>
            @if($keyword !== '')<span class="badge rounded-pill text-bg-light border">Từ khóa: {{ $keyword }}</span>@endif
            @if($categoryId)<span class="badge rounded-pill text-bg-light border">Danh mục: {{ optional($categories->firstWhere('id', (int) $categoryId))->name }}</span>@endif
            @if($minPrice !== null)<span class="badge rounded-pill text-bg-light border">Từ {{ \App\Support\Money::format($minPrice) }} đ</span>@endif
            @if($maxPrice !== null)<span class="badge rounded-pill text-bg-light border">Đến {{ \App\Support\Money::format($maxPrice) }} đ</span>@endif
            @if($sort !== 'newest')<span class="badge rounded-pill text-bg-light border">Sắp xếp: {{ ['price_asc' => 'Giá tăng dần', 'price_desc' => 'Giá giảm dần', 'name_asc' => 'Tên A–Z'][$sort] ?? 'Mới nhất' }}</span>@endif
        </div>
    @endif

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4">
        @forelse($products as $product)
            <div class="col">
                <article class="card product-card h-100">
                    <a href="{{ route('store.show', ['product' => $product->slug]) }}" class="text-decoration-none" aria-label="Xem chi tiết {{ $product->name }}">
                        @if($product->image)
                            <img src="{{ \\App\\Support\\ProductImages::url($product->image) }}" class="card-img-top product-image" alt="{{ $product->name }}" loading="lazy">
                        @else
                            <div class="product-image d-flex align-items-center justify-content-center text-muted">Chưa có ảnh sản phẩm</div>
                        @endif
                    </a>
                    <div class="card-body d-flex flex-column">
                        <p class="small text-muted mb-1">{{ $product->category->name ?? 'Khác' }}</p>
                        <h2 class="h5"><a href="{{ route('store.show', ['product' => $product->slug]) }}" class="text-dark text-decoration-none">{{ $product->name }}</a></h2>
                        <p class="fw-bold fs-5 text-danger mb-2">{{ \App\Support\Money::format($product->price) }} đ</p>
                        <p class="small text-muted">Còn {{ $product->quantity }} sản phẩm</p>

                        <div class="mt-auto d-grid gap-2">
                            <a href="{{ route('store.show', ['product' => $product->slug]) }}" class="btn btn-outline-dark">Xem chi tiết</a>
                            @if(auth()->check() && auth()->user()->role === 'admin')
                                <a class="btn btn-primary" href="{{ route('admin.products.edit', $product) }}">Quản lý sản phẩm</a>
                            @else
                                <form action="{{ route('cart.add', $product->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="btn btn-primary w-100">Thêm vào giỏ</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12">
                <div class="empty-state text-center p-5">
                    <h2 class="h5">Không tìm thấy sản phẩm phù hợp</h2>
                    <p class="text-muted">Thử thay đổi từ khóa, danh mục hoặc khoảng giá.</p>
                    <a href="{{ route('store.index') }}" class="btn btn-outline-dark">Xem tất cả sản phẩm</a>
                </div>
            </div>
        @endforelse
    </div>

    @if($products->hasPages())
        <nav class="mt-4" aria-label="Phân trang sản phẩm">
            {{ $products->links() }}
        </nav>
    @endif
</div>
@endsection
