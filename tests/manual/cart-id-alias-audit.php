<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\CartSnapshot;
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
    throw new RuntimeException('Unsafe database');
}
auth()->setUser(User::where('email', 'customer@gd3.example.test')->firstOrFail());
session()->forget(['cart', 'checkout_quote', 'last_checkout_order_id']);
DB::beginTransaction();
try {
    $product = Product::create(['category_id' => Category::where('slug', 'gd3-sneakers')->firstOrFail()->id,
        'name' => 'ID alias audit '.uniqid(), 'slug' => 'id-alias-'.uniqid(),
        'price' => '10.00', 'quantity' => 5, 'is_active' => true]);
    $cartController = new CartController;
    $cartController->add(Request::create('/gio-hang/them/'.$product->id, 'POST', ['quantity' => 3]), (string) $product->id);
    $cartController->add(Request::create('/gio-hang/them/0'.$product->id, 'POST', ['quantity' => 3]), '0'.$product->id);
    $keys = array_keys(session('cart'));
    $checkout = new CheckoutController;
    $checkout->index();
    $accepted = false;
    try {
        $checkout->store(Request::create('/checkout', 'POST', ['checkout_version' => session('checkout_quote.version'),
            'customer_name' => 'Alias buyer', 'phone' => '0900000000', 'address' => 'Test address', 'payment_method' => 'cod']));
        $accepted = true;
    } catch (ValidationException $exception) {
        // A fixed implementation may reject the alias/cart rather than accepting it.
    }
    $order = Order::whereKey(session('last_checkout_order_id'))->first();
    $stock = $product->fresh()->quantity;
    $sold = $order ? (int) $order->items()->sum('quantity') : 0;
    $result = ['database' => 'sneakershop_gd3_test', 'initial_stock' => 5, 'cart_keys' => $keys,
        'checkout_accepted' => $accepted, 'sold_quantity' => $sold, 'stock_after_checkout' => $stock,
        'stock_invariant_passed' => $stock >= 0 && $sold <= 5];
    $legacyResults = [];
    foreach (['overstock', 'within_stock', 'direct_old_quote'] as $mode) {
        $product->refresh()->update(['quantity' => 5]);
        $quantity = $mode === 'within_stock' ? 2 : 3;
        $raw = [$product->id => ['quantity' => $quantity, 'price' => '10.00'],
            '0'.$product->id => ['quantity' => $quantity, 'price' => '10.00']];
        session()->put('cart', $raw);
        session()->forget('last_checkout_order_id');
        if ($mode === 'direct_old_quote') {
            session()->put('checkout_quote', ['version' => bin2hex(random_bytes(32)), 'snapshot' => CartSnapshot::version($raw)]);
        } else {
            $checkout->index();
        }
        $before = Order::count();
        $accepted = false;
        try {
            $checkout->store(Request::create('/checkout', 'POST', ['checkout_version' => session('checkout_quote.version'),
                'customer_name' => 'Legacy alias buyer', 'phone' => '0900000000', 'address' => 'Test address', 'payment_method' => 'cod']));
            $accepted = true;
        } catch (ValidationException $exception) {
            // Overstock and unconfirmed normalization must fail without any writes.
        }
        $newOrders = Order::count() - $before;
        $stock = $product->fresh()->quantity;
        $pass = $mode === 'within_stock' ? $accepted && $newOrders === 1 && $stock === 1
            : ! $accepted && $newOrders === 0 && $stock === 5;
        if ($mode === 'within_stock') {
            $pass = $pass && Order::findOrFail(session('last_checkout_order_id'))->items()->count() === 1;
        }
        $legacyResults[] = ['case' => $mode, 'accepted' => $accepted, 'new_orders' => $newOrders, 'stock' => $stock, 'pass' => $pass];
        $result['stock_invariant_passed'] = $result['stock_invariant_passed'] && $pass;
    }
    $result['legacy_cases'] = $legacyResults;
} finally {
    DB::rollBack();
    session()->forget(['cart', 'checkout_quote', 'last_checkout_order_id']);
}
$result['all_fixture_writes_rolled_back'] = true;
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
exit($result['stock_invariant_passed'] ? 0 : 1);
