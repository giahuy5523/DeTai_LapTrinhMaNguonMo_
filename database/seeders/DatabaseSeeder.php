<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Do not create or promote accounts with a known default password.
        // Administrators must be provisioned explicitly through a trusted process.

        // Tạo sẵn vài Danh mục mẫu cho nhóm
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
