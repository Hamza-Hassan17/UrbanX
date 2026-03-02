<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantCartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'restaurant_item_id',
        'quantity',
        'price_per_item',
        'total_price',
    ];

    public function restaurantItem()
    {
        return $this->belongsTo(RestaurantItem::class, 'restaurant_item_id');
    }

    public function cart()
    {
        return $this->belongsTo(RestaurantCart::class, 'cart_id');
    }
}
