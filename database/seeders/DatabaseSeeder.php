<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tạo tài khoản Admin mẫu cho Tuấn test
        $admin = User::firstOrCreate(['email' => 'admin@gmail.com'], [
            'name' => 'Admin SneakerShop',
            'password' => bcrypt('12345678'), // Mật khẩu mã hóa
            'role' => 'admin',
        ]);
        if ($admin->role !== 'admin') {
            $admin->update(['role' => 'admin']);
        }

        // 2. Tạo sẵn vài Danh mục mẫu cho nhóm
        Category::firstOrCreate(['slug' => 'nike'], [
            'name' => 'Nike',
            'description' => 'Thương hiệu giày Nike'
        ]);
        
        Category::firstOrCreate(['slug' => 'adidas'], [
            'name' => 'Adidas',
            'description' => 'Thương hiệu giày Adidas'
        ]);
        
        // Chú ý: Không cần seed Sản phẩm vì nó dính tới file ảnh vật lý, cứ để test tay.
    }
}
