<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable =
    [
        'category_id', 'name', 'slug', 'price', 
        'quantity', 'image', 'description', 'is_active'
    ];

    // Mối quan hệ: 1 Sản phẩm thuộc về 1 Danh mục
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
