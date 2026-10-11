<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SneakerShop')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/shop.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="d-flex flex-column min-vh-100">
    <a class="visually-hidden-focusable p-3" href="#main-content">Đến nội dung chính</a>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ route('home') }}">Sneaker<span class="brand-accent">Shop</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#shopNav" aria-controls="shopNav" aria-expanded="false" aria-label="Mở menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="shopNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('store.*') ? 'active' : '' }}" href="{{ route('store.index') }}">Sản phẩm</a></li>
                    @if(!auth()->check() || auth()->user()->role === 'customer')
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('cart.*') ? 'active' : '' }}" href="{{ route('cart.index') }}">Giỏ hàng <span class="badge bg-light text-dark">{{ collect(session('cart', []))->sum('quantity') }}</span></a></li>
                    @endif
                </ul>
                <div class="d-flex flex-wrap align-items-center gap-3 py-2">
                    @auth
                        @if(auth()->user()->role === 'admin')
                            <a class="btn btn-outline-light btn-sm" href="{{ route('admin.dashboard') }}">Quản trị</a>
                        @endif
                        <span class="text-white small">{{ auth()->user()->name }}</span>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button class="btn btn-light btn-sm" type="submit">Đăng xuất</button>
                        </form>
                    @else
                        <a class="text-white text-decoration-none" href="{{ route('login') }}">Đăng nhập</a>
                        <a class="btn btn-light btn-sm" href="{{ route('register') }}">Đăng ký</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
    <main id="main-content" class="container py-4 py-lg-5 flex-grow-1">
        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
    <footer class="bg-dark text-white py-4">
        <div class="container d-flex flex-wrap justify-content-between gap-2">
            <strong>SneakerShop</strong>
            <span class="small">© {{ date('Y') }} · Website bán giày thể thao</span>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
