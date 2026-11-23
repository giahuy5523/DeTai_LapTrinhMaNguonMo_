<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\StockReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || DB::selectOne('SELECT DATABASE() AS db')->db !== 'sneakershop_gd3_test') {
    throw new RuntimeException('Only isolated sneakershop_gd3_test is allowed.');
}
if (($argv[1] ?? '') === 'worker') {
    [, , $userId, $productId, $barrier] = $argv;
    touch($barrier.'.'.getmypid().'.ready');
    $deadline = microtime(true) + 15;
    while (!is_file($barrier)) {
        if (microtime(true) > $deadline) throw new RuntimeException('Barrier timeout');
        usleep(10000);
    }
    try {
        (new StockReservationService)->reserve((int) $userId, bin2hex(random_bytes(32)), [(int) $productId => ['quantity' => 1]]);
        echo 'accepted';
    } catch (ValidationException $exception) {
        echo 'rejected';
    }
    exit;
}
$category = Category::create(['name'=>'Reservation audit '.uniqid(), 'slug'=>'reservation-audit-'.uniqid()]);
$product = Product::create(['category_id'=>$category->id,'name'=>'Reservation last pair','slug'=>'reservation-last-'.uniqid(),'price'=>'10.25','quantity'=>1,'is_active'=>true]);
$users = [User::factory()->create(['role'=>'customer']),User::factory()->create(['role'=>'customer'])];
$barrier = storage_path('framework/testing/reservation-'.bin2hex(random_bytes(8)));
if (!is_dir(dirname($barrier))) mkdir(dirname($barrier),0777,true);
$workers=[];
try {
    foreach ($users as $user) {
        $process=proc_open([PHP_BINARY,__FILE__,'worker',(string)$user->id,(string)$product->id,$barrier],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('Worker failed');
        fclose($pipes[0]);
        $workers[]=[$process,$pipes];
    }
    $deadline=microtime(true)+15;
    while(count(glob($barrier.'.*.ready'))<2) {
        if(microtime(true)>$deadline) throw new RuntimeException('Worker start timeout');
        usleep(10000);
    }
    touch($barrier);
    $results=[];
    foreach($workers as [$process,$pipes]) {
        $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0) throw new RuntimeException($err);
        $results[]=$out;
    }
    sort($results);
    if($results!==['accepted','rejected'] || $product->fresh()->quantity!==1) throw new RuntimeException('Reservation invariant failed');
    $row=DB::table('stock_reservations')->where('product_id',$product->id)->first();
    DB::table('stock_reservations')->where('id',$row->id)->update(['expires_at'=>now()->subSecond()]);
    (new StockReservationService)->reserve($users[1]->id,bin2hex(random_bytes(32)),[$product->id=>['quantity'=>1]]);
    echo json_encode(['engine'=>DB::selectOne('SELECT VERSION() AS v')->v,'race'=>$results,'stock_unchanged'=>true,'expired_hold_ignored'=>true,'result'=>'PASS']).PHP_EOL;
} finally {
    DB::table('stock_reservations')->where('product_id',$product->id)->delete();
    $product->delete();
    $category->delete();
    foreach($users as $user) $user->delete();
    foreach(glob($barrier.'*') as $file) unlink($file);
}