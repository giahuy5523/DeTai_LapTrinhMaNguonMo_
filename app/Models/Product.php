<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable =
        [
            'category_id', 'name', 'slug', 'price',
            'quantity', 'image', 'description', 'is_active',
        ];

    // Mối quan hệ: 1 Sản phẩm thuộc về 1 Danh mục
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function editVersion(): string
    {
        $snapshot = [];
        foreach (['id', 'category_id', 'name', 'quantity', 'image', 'description', 'is_active'] as $field) {
            $snapshot[$field] = (string) $this->getAttribute($field);
        }
        $snapshot['price'] = Money::cents($this->price);

        return hash_hmac('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), config('app.key'));
    }
}
