<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

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
$queries = [
    'negative_stock' => 'SELECT COUNT(*) AS n FROM products WHERE quantity < 0',
    'invalid_item_quantity' => 'SELECT COUNT(*) AS n FROM order_items WHERE quantity < 1',
    'wrong_item_subtotal' => 'SELECT COUNT(*) AS n FROM order_items WHERE subtotal <> quantity * unit_price',
    'wrong_order_total' => 'SELECT COUNT(*) AS n FROM orders o WHERE total_amount <> COALESCE((SELECT SUM(subtotal) FROM order_items i WHERE i.order_id = o.id), 0)',
    'duplicate_product_lines' => 'SELECT COUNT(*) AS n FROM (SELECT order_id, product_id FROM order_items WHERE product_id IS NOT NULL GROUP BY order_id, product_id HAVING COUNT(*) > 1) AS duplicates',
    'invalid_order_state' => "SELECT COUNT(*) AS n FROM orders WHERE status NOT IN ('pending','processing','completed','cancelled')",
    'orphan_order_items' => 'SELECT COUNT(*) AS n FROM order_items i LEFT JOIN orders o ON o.id = i.order_id WHERE o.id IS NULL',
];
$results = [];
foreach ($queries as $name => $sql) {
    $results[$name] = (int) DB::selectOne($sql)->n;
}
echo json_encode(['database' => 'sneakershop_gd3_test', 'read_only' => true, 'violations' => $results], JSON_PRETTY_PRINT).PHP_EOL;
exit(array_sum($results) === 0 ? 0 : 1);
