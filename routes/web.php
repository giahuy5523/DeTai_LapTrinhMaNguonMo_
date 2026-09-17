<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;

// Route trang chủ cho khách
Route::get('/', function () {
    return view('welcome'); // Đổi thành view trang chủ của bạn sau
});

// Nhóm Route dành cho Admin (sau này Tuấn sẽ thêm Middleware check đăng nhập vào đây)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('categories', CategoryController::class);
    Route::resource('products', ProductController::class);
});