<?php

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
    throw new RuntimeException('Unsafe database');
}
function replayWait(callable $condition): void
{
    $deadline = microtime(true) + 15;
    while (! $condition()) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Replay barrier timeout');
        }
        usleep(10000);
    }
}
if (($argv[1] ?? '') === 'worker') {
    [, , $userId, $productId, $token, $barrier] = $argv;
    auth()->setUser(User::findOrFail($userId));
    session()->put('cart', [$productId => ['quantity' => 1]]);
    (new CheckoutController)->index();
    $quote = session('checkout_quote');
    $quote['version'] = $token;
    session()->put('checkout_quote', $quote);
    $synchronized = false;
    DB::listen(function ($query) use (&$synchronized, $barrier) {
        if (! $synchronized && DB::transactionLevel() === 0
            && str_contains($query->sql, 'from `orders`') && str_contains($query->sql, '`checkout_token`')) {
            // Both workers have already read "no order" before either may begin checkout.
            $synchronized = true;
            touch($barrier.'.'.getmypid());
            replayWait(fn () => count(glob($barrier.'.*')) === 2);
        }
    });
    try {
        (new CheckoutController)->store(Request::create('/checkout', 'POST', [
            'checkout_version' => $token, 'customer_name' => 'Last unit replay',
            'phone' => '0900000000', 'address' => 'Test address', 'payment_method' => 'cod',
        ]));
        echo json_encode(['result' => 'accepted', 'order_id' => session('last_checkout_order_id')]);
    } catch (ValidationException $exception) {
        echo json_encode(['result' => 'rejected', 'errors' => $exception->errors()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
$user = User::where('email', 'customer@gd3.example.test')->firstOrFail();
$category = Category::where('slug', 'gd3-sneakers')->firstOrFail();
$product = Product::create(['category_id' => $category->id, 'name' => 'Replay last unit '.uniqid(),
    'slug' => 'replay-last-'.uniqid(), 'price' => '10.25', 'quantity' => 1, 'is_active' => true]);
$token = bin2hex(random_bytes(32));
$barrier = storage_path('framework/testing/last-replay-'.uniqid());
$workers = [];
for ($index = 0; $index < 2; $index++) {
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', (string) $user->id,
        (string) $product->id, $token, $barrier], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    $workers[] = [$process, $pipes];
}
$results = [];
foreach ($workers as [$process, $pipes]) {
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $results[] = $exit === 0 ? json_decode($out, true, 512, JSON_THROW_ON_ERROR)
        : ['result' => 'worker_error', 'details' => substr($err.$out, 0, 300)];
}
$orders = Order::where('checkout_token', $token)->get();
$count = $orders->count();
$stock = $product->fresh()->quantity;
$consistent = $count === 1 && $stock === 0;
$idempotent = array_column($results, 'result') === ['accepted', 'accepted']
    && count(array_unique(array_column($results, 'order_id'))) === 1;
auth()->setUser($user);
foreach ($orders as $order) {
    if ($order->status === 'pending') {
        (new CartController)->cancelOrder($order->id);
    }
}
$product->update(['is_active' => false]);
foreach (glob($barrier.'.*') as $file) {
    unlink($file);
}
echo json_encode(['case' => 'same_token_last_unit_after_both_initial_lookups', 'product_id' => $product->id,
    'workers' => $results, 'orders' => $count, 'stock_after_checkout' => $stock,
    'stock_after_cleanup' => $product->fresh()->quantity, 'no_duplicate_or_oversell' => $consistent,
    'both_receive_same_order' => $idempotent], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($consistent && $idempotent ? 0 : 1);
