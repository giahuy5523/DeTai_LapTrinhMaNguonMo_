<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sneakershop_gd3_test', 'SESSION_DRIVER' => 'array'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::selectOne('SELECT DATABASE() AS db')->db !== 'sneakershop_gd3_test') {
    throw new RuntimeException('Unsafe DB');
}
$category = Category::where('slug', 'gd3-sneakers')->firstOrFail();
$admin = User::where('email', 'admin@gd3.example.test')->firstOrFail();
$customer = User::where('email', 'customer@gd3.example.test')->firstOrFail();
$receiver = ['customer_name' => 'Deep mysql audit', 'phone' => '0900000000', 'address' => 'Deep audit', 'payment_method' => 'cod'];

function scenario(string $name, ?string $expectedError, callable $run): void
{
    DB::beginTransaction();
    try {
        try {
            $run();
            if ($expectedError !== null) {
                throw new RuntimeException('Expected validation error: '.$expectedError);
            }
        } catch (ValidationException $exception) {
            if ($expectedError === null || ! isset($exception->errors()[$expectedError])) {
                throw $exception;
            }
        }
        echo json_encode(['case' => $name, 'result' => 'PASS', 'validation' => $expectedError]).PHP_EOL;
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        session()->flush();
    }
}
function shoe(Category $category, string $price, int $quantity): Product
{
    return Product::create(['category_id' => $category->id, 'name' => 'Deep mysql '.uniqid(),
        'slug' => 'deep-mysql-'.uniqid(), 'price' => $price, 'quantity' => $quantity, 'is_active' => true]);
}
function checkout(Product $product, int $quantity, array $receiver): void
{
    session()->put('cart', [$product->id => ['quantity' => $quantity]]);
    $controller = new CheckoutController;
    $version = $controller->index()->getData()['checkoutVersion'];
    $controller->store(Request::create('/checkout', 'POST', $receiver + ['checkout_version' => $version]));
}

scenario('maximum_valid_price_is_stored_exactly', null, function () use ($category, $customer, $receiver) {
    auth()->setUser($customer);
    $product = shoe($category, '9999999999999.99', 2);
    checkout($product, 1, $receiver);
    $order = Order::latest('id')->firstOrFail();
    if ($order->total_amount !== '9999999999999.99' || $product->fresh()->quantity !== 1) {
        throw new RuntimeException('Money precision/stock failure');
    }
});
scenario('line_total_overflow_is_validation', 'cart', function () use ($category, $customer) {
    auth()->setUser($customer);
    $product = shoe($category, '6000000000000.00', 2);
    (new CartController)->add(Request::create('/cart', 'POST', ['quantity' => 2]), $product->id);
});
scenario('category_description_70000_bytes', 'description', function () use ($admin) {
    auth()->setUser($admin);
    (new CategoryController)->store(Request::create('/admin/categories', 'POST', ['name' => 'Long '.uniqid(), 'description' => str_repeat('a', 70000)]));
});
scenario('product_description_70000_bytes', 'description', function () use ($admin, $category) {
    auth()->setUser($admin);
    (new ProductController)->store(Request::create('/admin/products', 'POST', ['category_id' => $category->id, 'name' => 'Long '.uniqid(), 'price' => 100000, 'quantity' => 1, 'description' => str_repeat('a', 70000)]));
});
scenario('legacy_bad_stock_cancellation_is_validation', 'stock', function () use ($category, $customer, $receiver) {
    auth()->setUser($customer);
    $product = shoe($category, '100000.00', 2);
    checkout($product, 1, $receiver);
    $order = Order::latest('id')->firstOrFail();
    $product->update(['quantity' => 2147483647]);
    (new CartController)->cancelOrder($order->id);
});
scenario('admin_cannot_remove_room_for_returns', 'quantity', function () use ($category, $customer, $receiver, $admin) {
    auth()->setUser($customer);
    $product = shoe($category, '100000.00', 2);
    checkout($product, 1, $receiver);
    auth()->setUser($admin);
    $product->refresh();
    (new ProductController)->update(Request::create('/admin/products', 'PUT', $product->only(['category_id', 'name', 'price']) + ['quantity' => 2147483647, '_version' => $product->editVersion()]), $product);
});

$connection = config('database.connections.mysql');
$connection['database'] = 'ban_giay_the_thao';
config(['database.connections.app_readonly' => $connection]);
$db = DB::connection('app_readonly');
$counts = [];
foreach (['users', 'categories', 'products', 'orders', 'order_items'] as $table) {
    $counts[$table] = $db->table($table)->count();
}
echo json_encode(['application_database_read_only' => true, 'counts' => $counts]).PHP_EOL;
