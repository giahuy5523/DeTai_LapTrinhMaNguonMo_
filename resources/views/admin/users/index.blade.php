@extends('layouts.admin')

@section('title', 'Quản lý tài khoản')

@section('admin-content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0">Quản lý tài khoản</h1>
    <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}">Quản trị sản phẩm</a>
</div>

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form class="row g-2 mb-3" method="GET" action="{{ route('admin.users.index') }}">
    <div class="col-md-6"><input class="form-control" name="keyword" value="{{ $keyword }}" placeholder="Tìm theo tên hoặc email"></div>
    <div class="col-auto"><button class="btn btn-primary" type="submit">Tìm kiếm</button></div>
    @if($keyword !== '')<div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">Xóa lọc</a></div>@endif
</form>

<div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
        <thead class="table-dark"><tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead>
        <tbody>
            @forelse($users as $user)
                <tr>
                    <td>{{ $user->id }}</td><td>{{ $user->name }}</td><td>{{ $user->email }}</td>
                    <td>{{ $user->role === 'admin' ? 'Admin' : 'Khách hàng' }}</td>
                    <td>{{ $user->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-warning" href="{{ route('admin.users.edit', $user) }}">Sửa</a>
                        @if(!$user->is(auth()->user()))
                            <form class="d-inline" method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Bạn có chắc muốn xóa tài khoản này?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit">Xóa</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Không tìm thấy tài khoản.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $users->links() }}
@endsection
