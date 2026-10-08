<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Bán Giày Thể Thao</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .auth-card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .auth-card h2 { text-align: center; margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="email"], .form-group input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn-submit { width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        .btn-submit:hover { background-color: #0056b3; }
        .error { color: #dc3545; font-size: 14px; margin-top: 5px; }
        .alert-success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .auth-links { text-align: center; margin-top: 15px; font-size: 14px; }
        .auth-links a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>

<div class="auth-card">
    <h2>Đăng Nhập</h2>

    {{-- Hiển thị thông báo thành công (nếu có) --}}
    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus placeholder="Nhập email...">
            @error('email')
                <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">Mật khẩu</label>
            <input type="password" id="password" name="password" required placeholder="Nhập mật khẩu...">
            @error('password')
                <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label style="font-weight: normal; cursor: pointer;">
                <input type="checkbox" name="remember"> Ghi nhớ đăng nhập
            </label>
        </div>

        <button type="submit" class="btn-submit">Đăng nhập</button>

        <div class="auth-links">
            Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký ngay</a>
        </div>
    </form>
</div>

</body>
</html>