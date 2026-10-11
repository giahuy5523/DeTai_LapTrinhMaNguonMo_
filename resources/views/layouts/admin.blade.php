@extends('layouts.app')
@section('content')
<div class="row g-4">
    <aside class="col-lg-3">
        <div class="card border-0 p-3">
            <p class="small text-muted text-uppercase mb-2">Khu vực quản trị</p>
            <nav class="nav flex-column admin-nav" aria-label="Menu quản trị">
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Tổng quan</a>
                <a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">Danh mục</a>
                <a class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}">Sản phẩm</a>
                <a class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}">Đơn hàng</a>
                <a class="nav-link" href="{{ route('home') }}">← Về cửa hàng</a>
            </nav>
        </div>
    </aside>
    <section class="col-lg-9">
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="fw-bold mb-1">Vui lòng kiểm tra thông tin:</p>
                <ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('admin-content')
    </section>
</div>
@endsection
