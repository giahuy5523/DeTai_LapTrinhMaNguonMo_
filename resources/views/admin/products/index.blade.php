@extends('layouts.app')

@section('title', 'Quản lý Sản phẩm')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Danh sách Sản phẩm</h2>
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Thêm mới</a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="card-body">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Hình ảnh</th>
                    <th>Tên giày</th>
                    <th>Danh mục</th>
                    <th>Giá bán</th>
                    <th>Kho</th>
                    <th width="150">Hành động</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                <tr>
                    <td>{{ $product->id }}</td>
                    <td>
                        <!-- Hiển thị ảnh upload -->
                        @if($product->image)
                        <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" width="80" class="img-thumbnail">
                        @else
                        <span class="text-muted">Không có ảnh</span>
                        @endif
                    </td>
                    <td class="fw-bold">{{ $product->name }}</td>
                    <!-- Lấy tên danh mục từ mối quan hệ khóa ngoại -->
                    <td>{{ $product->category->name ?? 'N/A' }}</td>
                    <!-- Định dạng giá tiền VNĐ -->
                    <td>{{ number_format($product->price, 0, ',', '.') }} VNĐ</td>
                    <td>{{ $product->quantity }}</td>
                    <td class="d-flex gap-2">
                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-warning">Sửa</a>
                        <form action="{{ route('admin.products.destroy', $product) }}" method="POST" onsubmit="return confirm('Xóa sản phẩm này sẽ không thể khôi phục. Tiếp tục?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted">Chưa có sản phẩm nào.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-3">
            {{ $products->links() }}
        </div>
    </div>
</div>
@endsection