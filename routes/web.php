<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
<<<<<<< HEAD
use App\Http\Controllers\HomeController;
=======
use App\Http\Controllers\CartController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\CheckoutController;

>>>>>>> origin/feature/gio-hang-thanh-toan
// Route trang chủ cho khách
Route::get('/', function () {
    return view('welcome'); // Đổi thành view trang chủ của bạn sau
});

// Nhóm Route dành cho Admin (sau này Tuấn sẽ thêm Middleware check đăng nhập vào đây)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('categories', CategoryController::class);
    Route::resource('products', ProductController::class);
});


<<<<<<< HEAD
// Route trang chủ cho khách hàng
Route::get('/', [HomeController::class, 'index'])->name('home');
=======
Route::get('/gio-hang', [CartController::class, 'index'])
    ->name('cart.index');

Route::post('/gio-hang/them/{id}', [CartController::class, 'add'])
    ->name('cart.add');

Route::patch('/gio-hang/cap-nhat/{id}', [CartController::class, 'update'])
    ->name('cart.update');

Route::delete('/gio-hang/xoa/{id}', [CartController::class, 'remove'])
    ->name('cart.remove');
Route::get('/san-pham', [StoreController::class, 'index'])
    ->name('store.index');

Route::get('/checkout', [CheckoutController::class, 'index'])
    ->name('checkout.index');

Route::post('/checkout', [CheckoutController::class, 'store'])
    ->name('checkout.store');

Route::get('/checkout/thanh-cong', [CheckoutController::class, 'success'])
    ->name('checkout.success');
>>>>>>> origin/feature/gio-hang-thanh-toan
