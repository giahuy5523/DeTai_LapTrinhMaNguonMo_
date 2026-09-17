@extends('layouts.app')

@section('title', 'Thêm Danh mục mới')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Thêm Danh mục mới</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.categories.store') }}" method="POST">
                    @csrf <!-- Bắt buộc phải có @csrf trong form POST của Laravel -->
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="VD: Giày Nike, Giày Adidas...">
                        <!-- Hiển thị lỗi validation -->
                        @error('name') 
                            <div class="invalid-feedback">{{ $message }}</div> 
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Mô tả</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Nhập mô tả cho danh mục này...">{{ old('description') }}</textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">Hủy bỏ</a>
                        <button type="submit" class="btn btn-success">Lưu danh mục</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection