<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Layout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark p-2">
        <a class="navbar-brand" href="{{ route('admin.dashboard') }}">Admin Panel</a>
    </nav>

    <!-- Navbar chung -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">SneakerShop</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/') }}">Trang chủ</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('store.index') }}">Sản phẩm</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('cart.index') }}">Giỏ hàng</a>
                    </li>
                    <!-- Chỗ này Tuấn sẽ code hiển thị Tên User/Login/Logout sau -->
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        @yield('content')
    </div>

    <footer class="text-center mt-4">
        <hr>
        <p>&copy; {{ date('Y') }} SneakerShop Admin</p>
    </footer>
</body>
</html>
