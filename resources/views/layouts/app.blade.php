<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Shop Giày Thể Thao')</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Thêm CSS tùy chỉnh của dự án ở đây -->
    <style>
        /* CSS reset & base */
    </style>
</head>
<body>
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

    <!-- Main Content: Các view con sẽ chèn nội dung vào đây -->
    <main class="container my-5">
        @yield('content')
    </main>

    <!-- Footer chung -->
    <footer class="bg-dark text-white text-center py-3 mt-auto">
        <p class="mb-0">&copy; 2026 SneakerShop - Đồ án Lập trình mã nguồn mở</p>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Thêm jQuery hoặc JS tùy chỉnh (cho Trọng dùng AJAX giỏ hàng) -->
    @stack('scripts')
</body>
</html>