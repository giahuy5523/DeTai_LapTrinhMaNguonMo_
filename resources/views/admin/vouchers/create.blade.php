@extends('layouts.admin')

@section('title', 'Tạo voucher')

@section('admin-content')
<h1 class="h2 mb-4">Tạo voucher</h1>
<div class="card"><div class="card-body">
    <form method="POST" action="{{ route('admin.vouchers.store') }}">
        @csrf
        @include('admin.vouchers._form')
        <div class="d-flex justify-content-end gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('admin.vouchers.index') }}">Hủy</a>
            <button class="btn btn-primary" type="submit">Lưu voucher</button>
        </div>
    </form>
</div></div>
@endsection
