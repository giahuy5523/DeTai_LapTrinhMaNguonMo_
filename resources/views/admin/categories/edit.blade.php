@extends('layouts.admin')

@section('title', 'Chỉnh sửa Danh mục')

@section('admin-content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0">Chỉnh sửa Danh mục: {{ $category->name }}</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.categories.update', $category) }}" method="POST">
                    @csrf
                    @method('PUT') <!-- Bắt buộc dùng PUT để update dữ liệu -->

                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold">Tên danh mục <span class="text-danger">*</span></label>
                        <input id="name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ \App\Support\FormInput::old('name', $category->name) }}" maxlength="255" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">Mô tả</label>
                        <textarea id="description" name="description" class="form-control" rows="4">{{ \App\Support\FormInput::old('description', $category->description) }}</textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">Hủy bỏ</a>
                        <button type="submit" class="btn btn-warning">Cập nhật</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
