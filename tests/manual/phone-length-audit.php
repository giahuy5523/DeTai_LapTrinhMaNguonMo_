<?php

use App\Http\Controllers\CheckoutController;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
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
$columnLength = DB::selectOne("SELECT CHARACTER_MAXIMUM_LENGTH AS max_length FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'phone'")->max_length;
$results = [];
foreach ([20, 21] as $length) {
    DB::beginTransaction();
    try {
        $product = Product::create(['category_id' => Category::where('slug', 'gd3-sneakers')->firstOrFail()->id,
            'name' => 'Phone boundary '.uniqid(), 'slug' => 'phone-boundary-'.uniqid(),
            'price' => '12.50', 'quantity' => 3, 'is_active' => true]);
        $initialOrders = Order::count();
        session()->put('cart', [$product->id => ['quantity' => 1]]);
        (new CheckoutController)->index();
        $phone = '+0'.'12345678'.str_repeat('-', $length - 11).'9';
        $result = ['phone_length' => strlen($phone), 'sql_column_limit' => $columnLength];
        try {
            (new CheckoutController)->store(Request::create('/checkout', 'POST', [
                'checkout_version' => session('checkout_quote.version'), 'customer_name' => 'Boundary buyer',
                'phone' => $phone, 'address' => 'Test address', 'payment_method' => 'cod',
            ]));
            $result['actual'] = 'accepted';
        } catch (ValidationException $exception) {
            $result['actual'] = 'validation';
            $result['fields'] = array_keys($exception->errors());
        } catch (QueryException $exception) {
            $result['actual'] = 'sql_exception';
            $result['driver_code'] = $exception->errorInfo[1] ?? null;
        }
        $result['new_orders'] = Order::count() - $initialOrders;
        $result['stock'] = $product->fresh()->quantity;
        $results[] = $result;
    } finally {
        DB::rollBack();
        session()->forget(['cart', 'checkout_quote', 'last_checkout_order_id']);
    }
}
echo json_encode(['database' => 'sneakershop_gd3_test', 'all_fixture_writes_rolled_back' => true,
    'cases' => $results], JSON_PRETTY_PRINT).PHP_EOL;
exit($results[0]['actual'] === 'accepted' && $results[1]['actual'] === 'validation' ? 0 : 1);
