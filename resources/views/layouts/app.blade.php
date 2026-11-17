<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SneakerShop')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
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
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('cart.*') ? 'active' : '' }}" href="{{ route('cart.index') }}">Giỏ hàng <span id="navCartBadge" class="badge bg-light text-dark">{{ collect(session('cart', []))->sum('quantity') }}</span></a></li>
                    @endif
                </ul>
                <div class="d-flex flex-wrap align-items-center gap-3 py-2">
                    @auth
                        @if(auth()->user()->role === 'admin')
                            <a class="btn btn-outline-light btn-sm" href="{{ route('admin.dashboard') }}">Quản trị</a>
                            <a class="btn btn-outline-light btn-sm" href="{{ route('admin.users.index') }}">Quản lý tài khoản</a>
                        @endif
                        <a class="text-white text-decoration-none" href="{{ route('profile.edit') }}">Hồ sơ</a>
                        <span class="text-white small account-name">{{ auth()->user()->name }}</span>
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
        @if(session('warning'))
            <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
        @endif
        @yield('content')
    </main>
    <footer class="bg-dark text-white py-4">
        <div class="container d-flex flex-wrap justify-content-between gap-2">
            <strong>SneakerShop</strong>
            <span class="small">© {{ date('Y') }} · Website bán giày thể thao</span>
        </div>
    </footer>

    <div class="toast-container position-fixed bottom-0 end-0 p-3 mw-100">
        <div id="cartToast" class="toast align-items-center text-bg-success border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <span id="cartToastIcon" class="fw-bold fs-5">✓</span>
                    <span id="cartToastMessage">Đã thêm vào giỏ hàng!</span>
                </div>
                <a href="{{ route('cart.index') }}" class="btn btn-light btn-sm my-auto me-2 text-nowrap fw-semibold">Xem giỏ</a>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Đóng"></button>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toastEl = document.getElementById('cartToast');
            const toastMsg = document.getElementById('cartToastMessage');
            const toastIcon = document.getElementById('cartToastIcon');
            const cartBadge = document.getElementById('navCartBadge');
            const toast = (toastEl && window.bootstrap && bootstrap.Toast) ? new bootstrap.Toast(toastEl, { delay: 3500 }) : null;

            document.querySelectorAll('form[action*="/gio-hang/them/"]').forEach(function (form) {
                form.addEventListener('submit', async function (e) {
                    // Chặn hành vi submit thông thường để thực hiện AJAX
                    e.preventDefault();
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang thêm...';
                    }

                    try {
                        const formData = new FormData(form);
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        if (response.status === 401) {
                            window.location.href = '{{ route("login") }}';
                            return;
                        }

                        const data = await response.json();

                        if (response.ok && data.success) {
                            if (cartBadge) {
                                cartBadge.textContent = data.cart_count;
                                cartBadge.classList.add('badge-bump');
                                setTimeout(() => cartBadge.classList.remove('badge-bump'), 350);
                            }
                            if (toast && toastMsg) {
                                toastMsg.textContent = data.message || 'Đã thêm sản phẩm vào giỏ hàng!';
                                if (toastIcon) toastIcon.textContent = '✓';
                                toastEl.classList.remove('text-bg-danger');
                                toastEl.classList.add('text-bg-success');
                                toast.show();
                            }
                        } else {
                            const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Có lỗi xảy ra khi thêm vào giỏ hàng.');
                            if (toast && toastMsg) {
                                toastMsg.textContent = errorMsg;
                                if (toastIcon) toastIcon.textContent = '⚠';
                                toastEl.classList.remove('text-bg-success');
                                toastEl.classList.add('text-bg-danger');
                                toast.show();
                            } else {
                                alert(errorMsg);
                            }
                        }
                    } catch (err) {
                        const netErrorMsg = 'Không thể kết nối máy chủ. Vui lòng kiểm tra lại giỏ hàng trước khi thử lại.';
                        if (toast && toastMsg) {
                            toastMsg.textContent = netErrorMsg;
                            if (toastIcon) toastIcon.textContent = '⚠';
                            toastEl.classList.remove('text-bg-success');
                            toastEl.classList.add('text-bg-danger');
                            toast.show();
                        } else {
                            alert(netErrorMsg);
                        }
                    } finally {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalBtnText;
                        }
                    }
                });
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
