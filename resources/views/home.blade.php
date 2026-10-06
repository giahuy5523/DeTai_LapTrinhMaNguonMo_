@extends('layouts.app')

@section('title', 'Trang chủ - SneakerShop')

@section('content')
<div class="text-center mb-5">
    <h1 class="display-5 fw-bold">Chào mừng đến với SneakerShop</h1>
    <p class="lead text-muted">Khám phá những mẫu giày thể thao mới nhất</p>
</div>

<div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
    @forelse($newProducts as $product)
        <div class="col">
            <div class="card h-100 shadow-sm border-0 bg-light">
                <!-- Hiển thị ảnh, bo góc nhẹ -->
                @if($product->image)
                    <img src="{{ asset('storage/' . $product->image) }}" class="card-img-top rounded-top" alt="{{ $product->name }}" style="height: 250px; object-fit: cover;">
                @else
                    <img src="https://via.placeholder.com/250" class="card-img-top rounded-top" alt="No image" style="height: 250px; object-fit: cover;">
                @endif
                
                <div class="card-body d-flex flex-column">
                    <p class="text-muted mb-1 small">{{ $product->category->name ?? 'Khác' }}</p>
                    <h5 class="card-title fw-bold text-truncate" title="{{ $product->name }}">{{ $product->name }}</h5>
                    <p class="card-text text-danger fw-bold fs-5 mt-auto">
                        {{ number_format($product->price, 0, ',', '.') }} đ
                    </p>
                    
                    <!-- Nút Thêm vào giỏ hàng (Tạo bộ khung sẵn để Trọng ráp code sau) -->
                    <button class="btn btn-outline-dark w-100 mt-3">Thêm vào giỏ</button>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center mt-5">
            <p class="text-muted">Hiện tại chưa có sản phẩm nào được bày bán.</p>
        </div>
    @endforelse
</div>
@endsection