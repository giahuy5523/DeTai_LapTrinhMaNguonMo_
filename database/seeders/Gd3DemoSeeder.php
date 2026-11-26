<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Support\ProductImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class Gd3DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || DB::getDriverName() !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'sneakershop_gd3_test'
            || DB::selectOne('SELECT DATABASE() AS db')->db !== 'sneakershop_gd3_test') {
            throw new RuntimeException('Chỉ được seed vào MySQL sneakershop_gd3_test với APP_ENV=testing.');
        }
        $password = getenv('GD3_DEMO_PASSWORD');
        if (! $password || strlen($password) < 12) {
            throw new RuntimeException('Đặt GD3_DEMO_PASSWORD tối thiểu 12 ký tự; không ghi mật khẩu vào Git.');
        }
        // Validate existing identities before creating anything; never promote a user.
        $accounts = [
            'admin@demo.test' => ['name' => 'Demo Admin', 'role' => 'admin'],
            'customer1@demo.test' => ['name' => 'Demo Customer 1', 'role' => 'customer'],
            'customer2@demo.test' => ['name' => 'Demo Customer 2', 'role' => 'customer'],
        ];
        foreach ($accounts as $email => $account) {
            $user = User::where('email', $email)->first();
            if ($user && $user->role !== $account['role']) {
                throw new RuntimeException('Tài khoản demo có role khác; không tự thay đổi quyền.');
            }
        }
        DB::transaction(function () use ($password, $accounts) {
            foreach ($accounts as $email => $account) {
                User::firstOrCreate(['email' => $email], [
                    'name' => $account['name'], 'role' => $account['role'],
                    'password' => Hash::make($password),
                ]);
            }
            $category = Category::firstOrCreate(['slug' => 'gd3-sneakers'], ['name' => 'GD3 Sneakers']);
            $demoFiles = ProductImages::demoFiles();
            for ($i = 1; $i <= 15; $i++) {
                $sourceFile = $demoFiles[($i - 1) % count($demoFiles)];
                $sourcePath = public_path('images/products/'.$sourceFile);
                $contents = is_file($sourcePath) ? file_get_contents($sourcePath) : false;
                $image = 'products/gd3-demo-'.$i.'.jpg';
                if ($contents === false || ! Storage::disk('public')->put($image, $contents)) {
                    throw new RuntimeException('Không lưu được ảnh demo.');
                }
                $product = Product::firstOrCreate(['slug' => 'gd3-sneaker-'.$i], [
                    'category_id' => $category->id, 'name' => 'GD3 Sneaker '.$i,
                    'price' => 1200000 + $i * 10000, 'quantity' => $i === 12 ? 0 : 10,
                    'is_active' => $i !== 13, 'image' => $image,
                ]);
                if (str_ends_with((string) $product->image, '.svg')) {
                    $product->update(['image' => $image]);
                }
            }

            Voucher::firstOrCreate(['code' => 'DEMO10'], [
                'discount_type' => 'percentage', 'discount_value' => '10.00',
                'max_discount_amount' => '150000.00', 'minimum_order_amount' => '500000.00',
                'usage_limit' => 1, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(30),
                'is_active' => true,
            ]);
            Voucher::firstOrCreate(['code' => 'DEMO50K'], [
                'discount_type' => 'fixed', 'discount_value' => '50000.00',
                'max_discount_amount' => null, 'minimum_order_amount' => '0.00',
                'usage_limit' => 20, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(30),
                'is_active' => true,
            ]);
        });
    }
}
