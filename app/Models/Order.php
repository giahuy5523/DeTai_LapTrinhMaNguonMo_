<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'address',
        'total_amount',
        'status', // Các giá trị thống nhất với Trọng: pending, processing, completed, cancelled
        'note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}