<?php

declare(strict_types=1);

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Money;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Multi-process concurrency test for single-use voucher limit (usage_limit = 1).
 *
 * Usage:
 *   - Isolated MariaDB demo DB (default):  php tests/manual/voucher-concurrency.php
 *   - Isolated SQLite WAL temp DB:         php tests/manual/voucher-concurrency.php --sqlite
 */
$isWorker = ($argv[1] ?? '') === 'worker';
$useSqlite = in_array('--sqlite', $argv, true);
$sqlitePath = null;

if ($isWorker) {
    $driver = $argv[2];
    $dbTarget = $argv[3];
    $userId = (int) $argv[4];
    $productId = (int) $argv[5];
    $voucherCode = $argv[6];
    $barrier = $argv[7];

    $envOverrides = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => $driver,
        'DB_DATABASE' => $dbTarget,
        'SESSION_DRIVER' => 'array',
    ];
} else {
    if ($useSqlite) {
        $dir = __DIR__.'/../../storage/framework/testing';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $sqlitePath = $dir.'/voucher-concurrency-'.bin2hex(random_bytes(6)).'.sqlite';
        touch($sqlitePath);
        $envOverrides = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $sqlitePath,
            'SESSION_DRIVER' => 'array',
        ];
    } else {
        $envOverrides = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => 'sneakershop_gd3_test',
            'SESSION_DRIVER' => 'array',
        ];
    }
}

foreach ($envOverrides as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDriverName() === 'mysql') {
    if (config('database.connections.mysql.database') !== 'sneakershop_gd3_test'
        || DB::selectOne('SELECT DATABASE() AS db')->db !== 'sneakershop_gd3_test') {
        throw new RuntimeException('Refusing to run MySQL voucher concurrency outside sneakershop_gd3_test.');
    }
} elseif (DB::getDriverName() === 'sqlite') {
    DB::statement('PRAGMA journal_mode=WAL;');
    DB::statement('PRAGMA busy_timeout=5000;');
}

if ($isWorker) {
    auth()->setUser(User::findOrFail($userId));
    $product = Product::findOrFail($productId);
    session()->put('cart', [
        $product->id => [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (string) $product->price,
            'image' => $product->image,
            'quantity' => 1,
            'is_active' => true,
            'stock' => (int) $product->quantity,
        ],
    ]);

    $checkout = new CheckoutController;
    // Reserve disjoint products before the barrier so both workers contend specifically on the voucher row.
    $checkoutVersion = $checkout->index()->getData()['checkoutVersion'];

    touch($barrier.'.'.getmypid().'.ready');
    $deadline = microtime(true) + 15;
    while (! is_file($barrier)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Barrier timeout');
        }
        usleep(5000);
    }

    try {
        $request = Request::create('/checkout', 'POST', [
            'checkout_version' => $checkoutVersion,
            'customer_name' => 'Voucher Concurrent User '.$userId,
            'phone' => '0901234567',
            'address' => '123 Demo Street',
            'payment_method' => 'bank_transfer',
            'voucher_code' => $voucherCode,
        ]);
        $checkout->store($request);
        echo 'accepted';
    } catch (ValidationException $e) {
        if (! isset($e->errors()['voucher_code'])) {
            throw $e;
        }
        echo 'rejected';
    }
    exit(0);
}

$driver = DB::getDriverName();
$dbTarget = $driver === 'sqlite' ? $sqlitePath : 'sneakershop_gd3_test';
$cleanupIds = ['categories' => [], 'products' => [], 'vouchers' => [], 'users' => []];

try {
    if ($driver === 'sqlite') {
        Artisan::call('migrate', ['--force' => true]);
    }

    $category = Category::firstOrCreate(
        ['slug' => 'voucher-concurrency-audit'],
        ['name' => 'Voucher Concurrency Audit']
    );
    $cleanupIds['categories'][] = $category->id;

    $users = User::whereIn('email', ['customer1@demo.test', 'customer2@demo.test'])->orderBy('id')->get();
    if ($users->count() < 2) {
        $users = collect([
            User::create([
                'name' => 'Voucher Racer 1',
                'email' => 'racer1-'.bin2hex(random_bytes(3)).'@demo.test',
                'password' => Hash::make('demo123456'),
                'role' => 'customer',
            ]),
            User::create([
                'name' => 'Voucher Racer 2',
                'email' => 'racer2-'.bin2hex(random_bytes(3)).'@demo.test',
                'password' => Hash::make('demo123456'),
                'role' => 'customer',
            ]),
        ]);
        $cleanupIds['users'] = $users->pluck('id')->all();
    }

    $rounds = [];
    for ($round = 1; $round <= 3; $round++) {
        $suffix = bin2hex(random_bytes(3));
        $p1 = Product::create([
            'category_id' => $category->id,
            'name' => 'Voucher Race Shoe A '.$suffix,
            'slug' => 'voucher-race-a-'.$suffix,
            'price' => '1000000.00',
            'quantity' => 5,
            'is_active' => true,
        ]);
        $p2 = Product::create([
            'category_id' => $category->id,
            'name' => 'Voucher Race Shoe B '.$suffix,
            'slug' => 'voucher-race-b-'.$suffix,
            'price' => '1000000.00',
            'quantity' => 5,
            'is_active' => true,
        ]);
        $voucher = Voucher::create([
            'code' => 'RACE1-'.strtoupper($suffix),
            'discount_type' => 'percentage',
            'discount_value' => '10.00',
            'max_discount_amount' => '150000.00',
            'minimum_order_amount' => '500000.00',
            'usage_limit' => 1,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);
        $cleanupIds['products'][] = $p1->id;
        $cleanupIds['products'][] = $p2->id;
        $cleanupIds['vouchers'][] = $voucher->id;

        $barrier = storage_path('framework/testing/voucher-race-'.bin2hex(random_bytes(6)));
        if (! is_dir(dirname($barrier))) {
            mkdir(dirname($barrier), 0777, true);
        }

        $workers = [];
        foreach ([[$users[0], $p1], [$users[1], $p2]] as [$user, $product]) {
            $process = proc_open(
                [PHP_BINARY, __FILE__, 'worker', $driver, (string) $dbTarget, (string) $user->id, (string) $product->id, $voucher->code, $barrier],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            if (! is_resource($process)) {
                throw new RuntimeException('Failed to launch voucher worker');
            }
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }

        $deadline = microtime(true) + 15;
        while (count(glob($barrier.'.*.ready')) < 2) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Voucher workers did not reach barrier');
            }
            usleep(5000);
        }
        touch($barrier);

        $results = [];
        foreach ($workers as [$process, $pipes]) {
            $out = trim(stream_get_contents($pipes[1]));
            $err = trim(stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0) {
                throw new RuntimeException("Worker error: {$err} {$out}");
            }
            $results[] = $out;
        }
        foreach (glob($barrier.'*') as $f) {
            @unlink($f);
        }

        sort($results);
        if ($results !== ['accepted', 'rejected']) {
            throw new RuntimeException('Expected [accepted, rejected], got '.json_encode($results));
        }

        $voucher->refresh();
        $orders = Order::where('voucher_code', $voucher->code)->get();
        if ($voucher->used_count !== 1 || $orders->count() !== 1) {
            throw new RuntimeException("Invariant failed: used_count={$voucher->used_count}, orders={$orders->count()}");
        }

        $winningOrder = $orders->first();
        if (Money::cents($winningOrder->subtotal_amount) !== 100000000
            || Money::cents($winningOrder->discount_amount) !== 10000000
            || Money::cents($winningOrder->total_amount) !== 90000000) {
            throw new RuntimeException('Discount snapshot mismatch on winning order');
        }

        $stockSum = (int) $p1->fresh()->quantity + (int) $p2->fresh()->quantity;
        if ($stockSum !== 9) {
            throw new RuntimeException("Expected combined stock 9 (5 + 4), got {$stockSum}");
        }

        // Verify cancelling the winning order restores stock to 10 but does NOT restore voucher used_count
        auth()->setUser(User::findOrFail($winningOrder->user_id));
        (new CartController)->cancelOrder($winningOrder->id);
        $stockAfterCancel = (int) $p1->fresh()->quantity + (int) $p2->fresh()->quantity;
        if ($stockAfterCancel !== 10 || $voucher->fresh()->used_count !== 1) {
            throw new RuntimeException("Post-cancel invariant failed: stockSum={$stockAfterCancel}, used_count={$voucher->fresh()->used_count}");
        }

        if ($driver === 'mysql') {
            $p1->update(['is_active' => false]);
            $p2->update(['is_active' => false]);
        }

        $rounds[] = [
            'round' => $round,
            'voucher' => $voucher->code,
            'race' => $results,
            'used_count' => $voucher->fresh()->used_count,
            'orders_created' => 1,
            'discount_amount' => Money::decimal(Money::cents($winningOrder->discount_amount)),
            'total_amount' => Money::decimal(Money::cents($winningOrder->total_amount)),
            'stock_after_checkout_sum' => $stockSum,
            'stock_after_cancel_sum' => $stockAfterCancel,
        ];
    }

    $engine = $driver === 'mysql'
        ? DB::selectOne('SELECT VERSION() AS v')->v
        : 'sqlite '.DB::selectOne('SELECT sqlite_version() AS v')->v;

    echo json_encode([
        'driver' => $driver,
        'engine' => $engine,
        'result' => 'PASS',
        'rounds' => $rounds,
    ], JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    if ($driver === 'sqlite' && $sqlitePath) {
        DB::disconnect();
        @unlink($sqlitePath);
        @unlink($sqlitePath.'-wal');
        @unlink($sqlitePath.'-shm');
    }
}
