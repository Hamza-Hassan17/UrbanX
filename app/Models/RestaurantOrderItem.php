<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'restaurant_item_id',
        'quantity',
        'price_per_item',
        'total_price',
        'notes',
    ];
}
