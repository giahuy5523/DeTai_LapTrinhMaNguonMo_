@extends('layouts.app')

@section('title', 'Thêm Sản phẩm mới')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Thêm Sản phẩm mới</h4>
            </div>
            <div class="card-body">
                <!-- Chú ý thuộc tính enctype bắt buộc để upload file -->
                <form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Danh mục <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select @error('category_id') is-invalid @enderror">
                            <option value="">-- Chọn danh mục giày --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tên giày <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Giá bán (VNĐ) <span class="text-danger">*</span></label>
                            <input type="number" name="price" class="form-control" value="{{ old('price', 0) }}" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Số lượng kho <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" value="{{ old('quantity', 0) }}" min="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Ảnh đại diện</label>
                        <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" accept="image/*">
                        @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Mô tả chi tiết</label>
                        <textarea name="description" class="form-control" rows="4">{{ old('description') }}</textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">Hủy</a>
                        <button type="submit" class="btn btn-success">Lưu sản phẩm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection