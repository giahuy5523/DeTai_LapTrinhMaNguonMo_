<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - Bán Giày Thể Thao</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .auth-card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .auth-card h2 { text-align: center; margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn-submit { width: 100%; padding: 10px; background-color: #28a745; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        .btn-submit:hover { background-color: #218838; }
        .error { color: #dc3545; font-size: 14px; margin-top: 5px; }
        .auth-links { text-align: center; margin-top: 15px; font-size: 14px; }
        .auth-links a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>

<div class="auth-card">
    <h2>Đăng Ký Tài Khoản</h2>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="form-group">
            <label for="name">Họ và Tên</label>
            <input type="text" autocomplete="name" maxlength="255" id="name" name="name" value="{{ \App\Support\FormInput::old('name') }}" required autofocus placeholder="Nhập họ tên...">
            @error('name')
                <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" autocomplete="email" maxlength="255" id="email" name="email" value="{{ \App\Support\FormInput::old('email') }}" required placeholder="Nhập email...">
            @error('email')
                <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">Mật khẩu</label>
            <input type="password" autocomplete="new-password" minlength="6" maxlength="72" id="password" name="password" aria-describedby="password-help" required placeholder="Nhập mật khẩu...">
            <small id="password-help">Tối thiểu 6 ký tự, tối đa 72 byte UTF-8; ký tự có dấu có thể chiếm nhiều byte.</small>
            @error('password')
                <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">Xác nhận Mật khẩu</label>
            <input type="password" autocomplete="new-password" minlength="6" maxlength="72" id="password_confirmation" name="password_confirmation" required placeholder="Nhập lại mật khẩu...">
        </div>

        <button type="submit" class="btn-submit">Đăng ký</button>

        <div class="auth-links">
            Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a>
        </div>
    </form>
</div>

</body>
</html>
