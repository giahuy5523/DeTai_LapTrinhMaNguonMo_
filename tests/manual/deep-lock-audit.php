<?php

use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sneakershop_gd3_test', 'SESSION_DRIVER' => 'array'] as $k => $v) {
    putenv("$k=$v");
    $_ENV[$k] = $_SERVER[$k] = $v;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::selectOne('SELECT DATABASE() AS db')->db !== 'sneakershop_gd3_test') {
    throw new RuntimeException('Unsafe DB');
}
function waitFor($fn)
{
    $end = microtime(true) + 15;
    while (! $fn()) {
        if (microtime(true) > $end) {
            throw new RuntimeException('Barrier timeout');
        }usleep(10000);
    }
}
if (($argv[1] ?? '') === 'worker') {
    [$file,$mode,$operation,$uid,$target,$barrier] = $argv;
    auth()->setUser(User::findOrFail($uid));
    if ($operation === 'checkout') {
        $locked = false;
        DB::listen(function ($query) use (&$locked) {
            if (! $locked && str_contains(strtolower($query->sql), 'from `products`') && str_contains(strtolower($query->sql), 'for update')) {
                $locked = true;
                usleep(100000);
            }
        });
    }
    touch($barrier.'.'.getmypid().'.ready');
    waitFor(fn () => file_exists($barrier));
    try {
        if (in_array($operation, ['checkout', 'replay'], true)) {
            [$idsString, $sharedToken] = array_pad(explode('@', $target, 2), 2, null);
            $ids = explode(',', $idsString);
            session()->put('cart', array_fill_keys($ids, ['quantity' => 1]));
            $version = (new CheckoutController)->index()->getData()['checkoutVersion'];
            if ($sharedToken) {
                $quote = session('checkout_quote');
                $quote['version'] = $sharedToken;
                session()->put('checkout_quote', $quote);
                $version = $sharedToken;
            }
            (new CheckoutController)->store(Request::create('/checkout', 'POST', ['checkout_version' => $version, 'customer_name' => 'Deep lock audit', 'phone' => '0900000000', 'address' => 'Audit address', 'payment_method' => 'cod']));
        } elseif ($operation === 'admincancel') {
            (new OrderController)->updateStatus(Request::create('/admin/orders', 'PATCH', ['status' => 'cancelled']), Order::findOrFail($target));
        } else {
            (new CartController)->cancelOrder($target);
        }
        echo json_encode(['result' => 'accepted']);
    } catch (Throwable $e) {
        echo json_encode(['result' => 'rejected', 'exception' => get_class($e), 'driver_code' => $e instanceof QueryException ? $e->errorInfo[1] : null, 'http_status' => $e instanceof HttpException ? $e->getStatusCode() : null]);
    }exit;
}
function race($specs)
{
    $barrier = storage_path('framework/testing/deep-lock-'.uniqid());
    $workers = [];
    foreach ($specs as [$op,$uid,$target]) {
        $proc = proc_open([PHP_BINARY, __FILE__, 'worker', $op, (string) $uid, (string) $target, $barrier], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $workers[] = [$proc, $pipes];
    }

    waitFor(fn () => count(glob($barrier.'.*.ready')) === 2);
    touch($barrier);
    $results = [];
    foreach ($workers as [$proc,$pipes]) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        if ($exit) {
            throw new RuntimeException(substr($err.$out, 0, 300));
        }

        $results[] = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    foreach (glob($barrier.'*') as $f) {
        unlink($f);
    }

    return $results;
}
$cat = Category::where('slug', 'gd3-sneakers')->firstOrFail();
$products = [];
for ($i = 0; $i < 2; $i++) {
    $products[] = Product::create(['category_id' => $cat->id, 'name' => 'Deep lock '.uniqid(), 'slug' => 'deep-lock-'.uniqid(), 'price' => 100000, 'quantity' => 5, 'is_active' => true]);
}
$users = User::whereIn('email', ['customer@gd3.example.test', 'customer2@gd3.example.test'])->get();
$result = race([['checkout', $users[0]->id, $products[0]->id.','.$products[1]->id], ['checkout', $users[1]->id, $products[1]->id.','.$products[0]->id]]);
echo json_encode(['case' => 'reverse_product_lock_order', 'workers' => $result]).PHP_EOL;
if (array_column($result, 'result') !== ['accepted', 'accepted']) {
    throw new RuntimeException('Both checkouts must succeed');
}

$orders = Order::whereHas('items', fn ($q) => $q->where('product_id', $products[0]->id))->get();
if ($orders->count() !== 2) {
    throw new RuntimeException('Expected two orders');
}

$order = $orders->first();
$admin = User::where('email', 'admin@gd3.example.test')->firstOrFail();
$result = race([['admincancel', $admin->id, $order->id], ['cancel', $order->user_id, $order->id]]);
if (count(array_filter($result, fn ($worker) => $worker['result'] === 'accepted')) !== 1
    || $order->fresh()->status !== 'cancelled'
    || array_map(fn ($product) => $product->fresh()->quantity, $products) !== [4, 4]) {
    throw new RuntimeException('Mixed cancellation did not restore stock exactly once');
}
echo json_encode(['case' => 'admin_and_customer_cancel_same_order', 'workers' => $result, 'final_status' => $order->fresh()->status, 'stock' => array_map(fn ($p) => $p->fresh()->quantity, $products)]).PHP_EOL;
foreach ($orders as $remaining) {
    if ($remaining->fresh()->status === 'pending') {
        auth()->setUser(User::findOrFail($remaining->user_id));
        (new CartController)->cancelOrder($remaining->id);
    }
}

$sharedToken = bin2hex(random_bytes(32));
$target = $products[0]->id.'@'.$sharedToken;
$replay = race([['replay', $users[0]->id, $target], ['replay', $users[0]->id, $target]]);
if (array_column($replay, 'result') !== ['accepted', 'accepted']
    || Order::where('checkout_token', $sharedToken)->count() !== 1 || $products[0]->fresh()->quantity !== 4) {
    throw new RuntimeException('Database idempotency invariant failed');
}
echo json_encode(['case' => 'same_quote_without_shared_session_lock', 'orders' => 1, 'stock' => 4, 'result' => 'PASS']).PHP_EOL;
$replayedOrder = Order::where('checkout_token', $sharedToken)->firstOrFail();
auth()->setUser(User::findOrFail($replayedOrder->user_id));
(new CartController)->cancelOrder($replayedOrder->id);

foreach ($products as $p) {
    if ($p->fresh()->quantity !== 5) {
        throw new RuntimeException('Wrong stock after cancellation');
    }

    $p->update(['is_active' => false]);
}

echo 'PASS: sorted locks and single restock'.PHP_EOL;
