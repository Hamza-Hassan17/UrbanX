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

    public function optionGroups()
    {
        return $this->hasMany(RestaurantItemOptionGroup::class, 'restaurant_item_id')->orderBy('sort_order')->orderBy('id');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id');
    }

    /**
     * Base unit price before any add-ons -- the discounted price when set,
     * same rule addToCart has always used.
     */
    public function basePrice(): float
    {
        return (float) ($this->discount_price ?? $this->price);
    }
}
