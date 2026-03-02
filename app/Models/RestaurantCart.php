<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'customer_id',
        'voucher_code_id',
        'subtotal',
        'discount',
        'tax',
        'platform_fee',
        'delivery_fee',
        'total_price',
    ];

    public function cartItems()
    {
        return $this->hasMany(RestaurantCartItem::class, 'cart_id');
    }
}
