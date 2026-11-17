<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\AuthController;

// Route trang chủ cho khách
Route::get('/', function () {
    return view('welcome');
});

// --- ROUTES XÁC THỰC (AUTH) ---
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);

// --- ROUTE YÊU CẦU ĐĂNG NHẬP (CHO MỌI USER) ---
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// --- NHÓM ROUTE DÀNH RIÊNG CHO ADMIN (Yêu cầu phải đăng nhập & có quyền Admin) ---
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    // Dashboard Admin
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    // Quản lý Danh mục & Sản phẩm
    Route::resource('categories', CategoryController::class);
    Route::resource('products', ProductController::class);
});