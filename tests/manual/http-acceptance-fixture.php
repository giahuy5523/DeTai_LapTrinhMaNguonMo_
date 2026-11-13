<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sneakershop_gd3_test'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::selectOne('SELECT DATABASE() AS db')->db !== 'sneakershop_gd3_test') {
    throw new RuntimeException('Unsafe database');
}
$path = storage_path('app/gd3-full-http-fixture.json');
if (($argv[1] ?? '') === 'check') {
    $fixture = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $product = Product::findOrFail($fixture['product_id']);
    $orders = Order::whereHas('items', fn ($q) => $q->where('product_id', $product->id))->with('items')->get();
    $valid = $orders->count() === 1 && $orders->first()->status === 'cancelled'
        && (int) $orders->first()->items->sum('quantity') === 3 && $product->quantity === 5;
    $product->update(['is_active' => false]);
    echo json_encode(['fixture' => 'full-http', 'orders' => $orders->count(), 'stock' => $product->quantity,
        'cancelled_after_single_purchase' => $valid]).PHP_EOL;
    exit($valid ? 0 : 1);
}
$suffix = bin2hex(random_bytes(6));
$password = bin2hex(random_bytes(24));
$email = 'qa-'.$suffix.'@gd3.example.test';
$user = User::create(['name' => str_repeat('W', 255), 'email' => $email,
    'password' => Hash::make($password), 'role' => 'customer']);
$product = Product::create(['category_id' => Category::where('slug', 'gd3-sneakers')->firstOrFail()->id,
    'name' => 'HTTP acceptance '.$suffix, 'slug' => 'http-acceptance-'.$suffix,
    'price' => '12.50', 'quantity' => 5, 'is_active' => true]);
file_put_contents($path, json_encode(['email' => $email, 'password' => $password, 'user_id' => $user->id,
    'product_id' => $product->id], JSON_THROW_ON_ERROR));
echo 'Created isolated HTTP fixture; credentials saved in ignored storage only.'.PHP_EOL;
