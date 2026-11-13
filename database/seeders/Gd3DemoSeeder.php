<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
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
        foreach (['admin', 'customer', 'customer2'] as $name) {
            $user = User::where('email', $name.'@gd3.example.test')->first();
            $role = $name === 'admin' ? 'admin' : 'customer';
            if ($user && $user->role !== $role) {
                throw new RuntimeException('Tài khoản demo có role khác; không tự thay đổi quyền.');
            }
        }
        DB::transaction(function () use ($password) {
            foreach (['admin', 'customer', 'customer2'] as $name) {
                User::firstOrCreate(['email' => $name.'@gd3.example.test'], [
                    'name' => 'GD3 '.$name, 'role' => $name === 'admin' ? 'admin' : 'customer',
                    'password' => Hash::make($password),
                ]);
            }
            $category = Category::firstOrCreate(['slug' => 'gd3-sneakers'], ['name' => 'GD3 Sneakers']);
            for ($i = 1; $i <= 13; $i++) {
                $image = 'products/gd3-demo-'.$i.'.svg';
                if (! Storage::disk('public')->put($image, file_get_contents(public_path('images/demo-sneaker.svg')))) {
                    throw new RuntimeException('Không lưu được ảnh demo.');
                }
                Product::firstOrCreate(['slug' => 'gd3-sneaker-'.$i], [
                    'category_id' => $category->id, 'name' => 'GD3 Sneaker '.$i,
                    'price' => 1200000 + $i * 10000, 'quantity' => $i === 12 ? 0 : 10,
                    'is_active' => $i !== 13, 'image' => $image,
                ]);
            }
        });
    }
}
