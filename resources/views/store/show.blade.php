
@extends('layouts.app')

@section('title', $product->name . ' - SneakerShop')

@section('content')
<div class="container">

    <div class="mb-4">
        <a href="{{ route('store.index') }}"
           class="btn btn-outline-secondary btn-sm">
            ← Quay lại cửa hàng
        </a>
    </div>

    <div class="row g-4">

        {{-- Hình ảnh sản phẩm --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
                @if($product->image)
                    <img
                        src="{{ \\App\\Support\\ProductImages::url($product->image) }}"
                        class="img-fluid w-100 rounded"
                        alt="{{ $product->name }}"
                        style="max-height: 520px; object-fit: contain;"
                    >
                @else
                    <div class="d-flex align-items-center justify-content-center bg-light"
                         style="height: 400px;">
                        <span class="text-muted">
                            Chưa có ảnh sản phẩm
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Thông tin sản phẩm --}}
        <div class="col-12 col-lg-6">

            <p class="text-muted mb-2">
                {{ $product->category->name ?? 'Khác' }}
            </p>

            <h1 class="fw-bold mb-3">
                {{ $product->name }}
            </h1>

            <h2 class="text-danger fw-bold mb-3">
                {{ \App\Support\Money::format($product->price) }} đ
            </h2>

            <div class="mb-3">
                @if((int) $product->quantity > 0)
                    <span class="badge bg-success">
                        Còn hàng
                    </span>

                    <span class="text-muted ms-2">
                        Còn {{ $product->quantity }} sản phẩm
                    </span>
                @else
                    <span class="badge bg-danger">
                        Hết hàng
                    </span>
                @endif
            </div>

            <hr>

            <h5 class="fw-bold">Mô tả sản phẩm</h5>

            <p class="text-secondary" style="white-space: pre-line;">
                {{ $product->description ?: 'Sản phẩm chưa có mô tả.' }}
            </p>

            <hr>

            {{-- Nút mua hàng --}}
            @if(auth()->check() && auth()->user()->role === 'admin')

                <a href="{{ route('admin.products.edit', $product) }}"
                   class="btn btn-outline-primary">
                    Quản lý sản phẩm
                </a>

            @elseif((int) $product->quantity > 0)

                <form action="{{ route('cart.add', $product->id) }}"
                      method="POST"
                      class="mt-3">

                    @csrf

                    <label for="quantity" class="form-label fw-semibold">
                        Số lượng
                    </label>

                    <div class="d-flex gap-2">
                        <input
                            type="number"
                            name="quantity"
                            id="quantity"
                            class="form-control"
                            value="1"
                            min="1"
                            max="{{ $product->quantity }}"
                            required
                            style="max-width: 110px;"
                        >

                        <button type="submit" class="btn btn-primary flex-grow-1">
                            Thêm vào giỏ hàng
                        </button>
                    </div>

                </form>

            @else

                <button class="btn btn-secondary" disabled>
                    Sản phẩm hết hàng
                </button>

            @endif

        </div>
    </div>

    {{-- Sản phẩm liên quan --}}
    @if($relatedProducts->isNotEmpty())

        <section class="mt-5">

            <h3 class="fw-bold mb-4">
                Sản phẩm liên quan
            </h3>

            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">

                @foreach($relatedProducts as $related)

                    <div class="col">
                        <div class="card h-100 shadow-sm">

                            <a href="{{ route('store.show', ['product' => $related->slug]) }}">
                                @if($related->image)
                                    <img
                                        src="{{ \\App\\Support\\ProductImages::url($related->image) }}"
                                        class="card-img-top"
                                        alt="{{ $related->name }}"
                                        style="height: 200px; object-fit: contain;"
                                        loading="lazy"
                                    >
                                @else
                                    <div class="bg-light d-flex align-items-center justify-content-center"
                                         style="height: 200px;">
                                        Chưa có ảnh
                                    </div>
                                @endif
                            </a>

                            <div class="card-body">

                                <h5 class="card-title">
                                    <a href="{{ route('store.show', ['product' => $related->slug]) }}"
                                       class="text-decoration-none text-dark">
                                        {{ $related->name }}
                                    </a>
                                </h5>

                                <p class="fw-bold text-danger mb-0">
                                    {{ \App\Support\Money::format($related->price) }} đ
                                </p>

                            </div>
                        </div>
                    </div>

                @endforeach

            </div>
        </section>

    @endif

</div>
@endsection
