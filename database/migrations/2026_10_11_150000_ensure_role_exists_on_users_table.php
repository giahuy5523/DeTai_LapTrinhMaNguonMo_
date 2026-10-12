<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DB cũ đã chạy create_users_table trước khi role được thêm vào source.
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('customer')->after('password');
            });
        }
    }

    public function down(): void
    {
        // Giữ role: schema nền hiện cũng khai báo cột này.
        // Drop cột khi rollback riêng migration sửa DB sẽ làm auth ngừng hoạt động.
    }
};
