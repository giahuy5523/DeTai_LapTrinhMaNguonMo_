<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\AuthController;

// --- TRANG CHỦ & KHÁCH HÀNG ---
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/san-pham', [StoreController::class, 'index'])->name('store.index');

Route::get('/gio-hang', [CartController::class, 'index'])->name('cart.index');
Route::post('/gio-hang/them/{id}', [CartController::class, 'add'])->name('cart.add');
Route::patch('/gio-hang/cap-nhat/{id}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/gio-hang/xoa/{id}', [CartController::class, 'remove'])->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/thanh-cong', [CheckoutController::class, 'success'])->name('checkout.success');

// --- ROUTES XÁC THỰC (AUTH) ---
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// --- NHÓM ROUTE DÀNH RIÊNG CHO ADMIN (Yêu cầu phải đăng nhập & có quyền Admin) ---
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    // Dashboard Admin
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    // Quản lý Danh mục, Sản phẩm, Đơn hàng
    Route::resource('categories', CategoryController::class);
    Route::resource('products', ProductController::class);
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
});

