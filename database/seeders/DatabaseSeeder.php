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
        User::create([
            'name' => 'Admin SneakerShop',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('12345678'), // Mật khẩu mã hóa
        ]);

        // 2. Tạo sẵn vài Danh mục mẫu cho nhóm
        Category::create([
            'name' => 'Nike',
            'slug' => 'nike',
            'description' => 'Thương hiệu giày Nike'
        ]);
        
        Category::create([
            'name' => 'Adidas',
            'slug' => 'adidas',
            'description' => 'Thương hiệu giày Adidas'
        ]);
        
        // Chú ý: Không cần seed Sản phẩm vì nó dính tới file ảnh vật lý, cứ để test tay.
    }
}