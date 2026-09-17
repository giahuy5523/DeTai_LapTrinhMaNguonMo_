<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        // Khóa ngoại liên kết với bảng categories
        $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
        
        $table->string('name'); // Tên giày
        $table->string('slug')->unique(); // Đường dẫn chuẩn SEO
        $table->decimal('price', 15, 2); // Giá bán
        $table->integer('quantity')->default(0); // Số lượng tồn kho
        $table->string('image')->nullable(); // Tên file ảnh đại diện
        $table->text('description')->nullable(); // Mô tả chi tiết giày
        $table->boolean('is_active')->default(true); // Trạng thái Còn bán / Ngừng bán
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
