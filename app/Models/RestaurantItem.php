<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'restaurant_menu_id',
        'name',
        'description',
        'price',
        'discount_percentage',
        'discount_price',
        'image',
        'is_available',
        'is_featured',
        'preparation_time',
        'is_active',
    ];
}
