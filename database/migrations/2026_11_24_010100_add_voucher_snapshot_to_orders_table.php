<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('user_id')
                ->constrained('vouchers')->nullOnDelete();
            $table->string('voucher_code', 32)->nullable()->after('voucher_id');
            $table->decimal('subtotal_amount', 15, 2)->default(0)->after('total_amount');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('subtotal_amount');
        });

        DB::table('orders')->update(['subtotal_amount' => DB::raw('total_amount')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_id');
            $table->dropColumn(['voucher_code', 'subtotal_amount', 'discount_amount']);
        });
    }
};
