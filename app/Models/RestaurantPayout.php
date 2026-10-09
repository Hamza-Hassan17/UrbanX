<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantPayout extends Model
{
    protected $fillable = [
        'restaurant_id',
        'period_start',
        'period_end',
        'sales_full_price',
        'restaurant_funded_discounts',
        'commission',
        'payable_amount',
        'method',
        'reference',
        'paid_by',
        'paid_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
