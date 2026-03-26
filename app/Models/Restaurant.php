<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'restaurant_category_id',
        'name',
        'description',
        'logo',
        'cover_image',
        'address',
        'latitude',
        'longitude',
        'weekly_schedule',
        'special_opening_hours',
        'is_open',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(RestaurantCategory::class, 'restaurant_category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(RestaurantOrder::class, 'restaurant_id');
    }

    public function menus()
    {
        return $this->hasMany(RestaurantMenu::class, 'restaurant_id');
    }

    public function reviews()
    {
        return $this->hasMany(RestaurantReview::class, 'restaurant_id');
    }
}
