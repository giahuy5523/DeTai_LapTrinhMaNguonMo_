@extends('layouts.admin')

@section('title', 'Sửa voucher')

@section('admin-content')
<h1 class="h2 mb-4">Sửa voucher {{ $voucher->code }}</h1>
<div class="card"><div class="card-body">
    <form method="POST" action="{{ route('admin.vouchers.update', $voucher) }}">
        @csrf @method('PUT')
        @include('admin.vouchers._form')
        <div class="d-flex justify-content-end gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('admin.vouchers.index') }}">Hủy</a>
            <button class="btn btn-primary" type="submit">Cập nhật</button>
        </div>
    </form>
</div></div>
@endsection
