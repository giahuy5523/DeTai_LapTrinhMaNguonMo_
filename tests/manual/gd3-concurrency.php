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
use Symfony\Component\HttpKernel\Exception\HttpException;

// Manual MySQL test. Never connects to the application database.
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sneakershop_gd3_test', 'SESSION_DRIVER' => 'array'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'sneakershop_gd3_test'
    || DB::selectOne('SELECT DATABASE() AS db')->db !== 'sneakershop_gd3_test') {
    throw new RuntimeException('Refusing to run outside the isolated GD3 database.');
}

if (($argv[1] ?? '') === 'worker') {
    [$script, $mode, $operation, $userId, $targetId, $barrier] = $argv;
    auth()->setUser(User::findOrFail($userId));
    touch($barrier.'.'.getmypid().'.ready');
    $deadline = microtime(true) + 15;
    while (! file_exists($barrier)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Barrier timeout');
        }
        usleep(10000);
    }
    try {
        if ($operation === 'checkout') {
            session()->put('cart', [$targetId => ['quantity' => 1, 'price' => 1]]);
            $request = Request::create('/checkout', 'POST', [
                'checkout_version' => (new CheckoutController)->index()->getData()['checkoutVersion'], 'customer_name' => 'GD3 concurrent', 'phone' => '0900000000',
                'address' => 'GD3 test address', 'payment_method' => 'cod',
            ]);
            (new CheckoutController)->store($request);
        } else {
            (new CartController)->cancelOrder($targetId);
        }
        echo 'accepted';
    } catch (ValidationException $e) {
        if (! isset($e->errors()['cart'])) {
            throw $e;
        }
        echo 'rejected';
    } catch (HttpException $e) {
        if ($e->getStatusCode() !== 422) {
            throw $e;
        }
        echo 'rejected';
    }
    exit;
}

function race(string $operation, array $users, int $targetId): array
{
    $barrier = storage_path('framework/testing/gd3-'.bin2hex(random_bytes(6)));
    if (! is_dir(dirname($barrier))) {
        mkdir(dirname($barrier), 0777, true);
    }
    $workers = [];
    foreach ($users as $user) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $operation, (string) $user->id, (string) $targetId, $barrier],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Worker could not start');
        }
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $deadline = microtime(true) + 15;
    while (count(glob($barrier.'.*.ready')) < 2) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Workers not ready');
        }
        usleep(10000);
    }
    touch($barrier);
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException($error.$output);
        }
        $results[] = trim($output);
    }
    foreach (glob($barrier.'*') as $file) {
        unlink($file);
    }
    sort($results);
    if ($results !== ['accepted', 'rejected']) {
        throw new RuntimeException(json_encode($results));
    }

    return $results;
}

$category = Category::firstOrCreate(['slug' => 'gd3-concurrency'], ['name' => 'GD3 concurrency']);
$users = User::whereIn('email', ['customer@gd3.example.test', 'customer2@gd3.example.test'])->get();
if ($users->count() !== 2) {
    throw new RuntimeException('Set up the two GD3 customer accounts first.');
}
$report = [];
for ($round = 1; $round <= 3; $round++) {
    $product = Product::create([
        'category_id' => $category->id, 'name' => 'GD3 race '.uniqid(),
        'slug' => 'gd3-race-'.uniqid(), 'price' => 100000, 'quantity' => 1, 'is_active' => true,
    ]);
    $checkout = race('checkout', $users->all(), $product->id);
    $order = Order::whereHas('items', fn ($query) => $query->where('product_id', $product->id))->sole();
    if ($product->fresh()->quantity !== 0 || (float) $order->total_amount !== 100000.0) {
        throw new RuntimeException('Checkout stock or price invariant failed');
    }
    $owner = User::findOrFail($order->user_id);
    $cancel = race('cancel', [$owner, $owner], $order->id);
    if ($product->fresh()->quantity !== 1 || $order->fresh()->status !== 'cancelled') {
        throw new RuntimeException('Cancellation stock invariant failed');
    }
    // Retain evidence without cluttering the customer-facing demo catalog.
    $product->update(['is_active' => false]);
    $report[] = ['round' => $round, 'checkout' => $checkout, 'cancel' => $cancel, 'final_stock' => 1];
}
echo json_encode(['database' => 'sneakershop_gd3_test', 'engine' => DB::selectOne('SELECT VERSION() AS version')->version,
    'result' => 'PASS', 'rounds' => $report], JSON_PRETTY_PRINT).PHP_EOL;
