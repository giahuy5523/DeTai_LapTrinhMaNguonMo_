<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // develop đã có cột role trong create_users_table, chỉ thêm khi chưa có
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('customer')->after('password');
            });
        }
    }

    public function down(): void
    {
        // Không xóa cột role: cột này do create_users_table tạo ra,
        // xóa ở đây sẽ làm phần đăng nhập/phân quyền ngừng hoạt động
    }
};
