<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Read-only application DB preparation: backup + SQL preview, never apply migrations.
foreach (['APP_ENV' => 'local', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'ban_giay_the_thao'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if ($connection->getDriverName() !== 'mysql'
    || DB::selectOne('SELECT DATABASE() AS db')->db !== 'ban_giay_the_thao') {
    throw new RuntimeException('Refusing release preparation outside the named application database.');
}
$directory = storage_path('app/backups');
if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
    throw new RuntimeException('Cannot create private backup directory.');
}
$binary = getenv('MYSQLDUMP_BIN') ?: 'C:/xampp/mysql/bin/mysqldump.exe';
if (! is_file($binary)) {
    throw new RuntimeException('Set MYSQLDUMP_BIN to a verified mysqldump executable.');
}
$prefix = $directory.'/before-gd3-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
$optionsPath = $prefix.'.cnf';
$dumpPath = $prefix.'.sql';
$config = $connection->getConfig();
$escape = static fn (string $value): string => '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']).'"';
$options = "[client]\n";
foreach (['host' => $config['host'], 'port' => (string) ($config['port'] ?? 3306),
    'user' => $config['username'], 'password' => $config['password'] ?? ''] as $key => $value) {
    $options .= $key.'='.$escape((string) $value)."\n";
}
if (file_put_contents($optionsPath, $options, LOCK_EX) === false) {
    throw new RuntimeException('Cannot write temporary client options.');
}
try {
    $process = proc_open([$binary, '--defaults-extra-file='.$optionsPath, '--single-transaction',
        '--routines', '--triggers', '--events', '--default-character-set=utf8mb4',
        '--result-file='.$dumpPath, 'ban_giay_the_thao'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot start backup process.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        // Do not log database data or temporary credential content.
        throw new RuntimeException('Database backup failed; no migration was applied.');
    }
} finally {
    if (is_file($optionsPath)) {
        unlink($optionsPath);
    }
}
$dump = file_get_contents($dumpPath);
$tables = (int) DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\'')->n;
if (! $dump || substr_count($dump, 'CREATE TABLE `') !== $tables || ! str_contains($dump, '-- Dump completed on')) {
    throw new RuntimeException('Backup validation failed; no migration was applied.');
}
$counts = [];
foreach (['users', 'categories', 'products', 'orders', 'order_items'] as $table) {
    $counts[$table] = DB::table($table)->count();
}
$migration = 'database/migrations/2026_10_23_001000_add_checkout_token_to_orders_table.php';
if (Artisan::call('migrate', ['--path' => $migration, '--pretend' => true, '--force' => true]) !== 0) {
    throw new RuntimeException('Cannot preview migration.');
}
$preview = Artisan::output();
$manifest = ['database' => 'ban_giay_the_thao', 'backup_file' => basename($dumpPath),
    'backup_bytes' => filesize($dumpPath), 'sha256' => hash_file('sha256', $dumpPath),
    'tables' => $tables, 'counts' => $counts, 'migration_applied' => false];
file_put_contents($prefix.'.manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
file_put_contents($prefix.'.migration-preview.txt', $preview);
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
echo $preview;
