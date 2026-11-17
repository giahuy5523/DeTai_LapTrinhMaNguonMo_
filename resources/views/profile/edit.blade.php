@extends('layouts.app')

@section('title', 'Hồ sơ cá nhân')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header"><h1 class="h4 mb-0">Hồ sơ cá nhân</h1></div>
            <div class="card-body">
                <p class="text-muted">Vai trò: {{ $user->role === 'admin' ? 'Quản trị viên' : 'Khách hàng' }}</p>
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="name">Họ tên</label>
                        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <hr>
                    <h2 class="h5">Đổi mật khẩu (không bắt buộc)</h2>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Mật khẩu hiện tại</label>
                        <input class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" type="password" autocomplete="current-password">
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu mới</label>
                        <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Nhập lại mật khẩu mới</label>
                        <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary" type="submit">Lưu thay đổi</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
