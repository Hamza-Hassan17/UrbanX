<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantItemOption extends Model
{
    protected $fillable = [
        'option_group_id',
        'name',
        'price_adjustment',
        'is_available',
        'sort_order',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'is_available' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(RestaurantItemOptionGroup::class, 'option_group_id');
    }
}
