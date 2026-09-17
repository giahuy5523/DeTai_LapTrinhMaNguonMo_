@extends('layouts.app')

@section('title', 'Quản lý Danh mục')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Danh sách Danh mục</h2>
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">Thêm mới</a>
</div>

<!-- Hiển thị thông báo thành công -->
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="card-body">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Tên danh mục</th>
                    <th>Slug URL</th>
                    <th>Mô tả</th>
                    <th width="150">Hành động</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                <tr>
                    <td>{{ $category->id }}</td>
                    <td class="fw-bold">{{ $category->name }}</td>
                    <td>{{ $category->slug }}</td>
                    <td>{{ $category->description }}</td>
                    <td class="d-flex gap-2">
                        <!-- Nút Sửa -->
                        <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-warning">Sửa</a>

                        <!-- Form Xóa (Bắt buộc dùng form để gửi method DELETE) -->
                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục này? Các sản phẩm bên trong cũng sẽ bị xóa theo!');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted">Chưa có danh mục nào.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Phân trang -->
        <div class="mt-3">
            {{ $categories->links() }}
        </div>
    </div>
</div>
@endsection