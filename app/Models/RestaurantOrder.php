<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'restaurant_id',
        'customer_id',
        'voucher_code_id',
        'subtotal',
        'discount',
        'tax',
        'platform_fee',
        'delivery_fee',
        'total_price',
        'status',
        'delivery_lat',
        'delivery_lang',
        'rider_note',
        'payment_method',
        'payment_status',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function voucherCode()
    {
        return $this->belongsTo(VoucherCode::class, 'voucher_code_id');
    }

    public function items()
    {
        return $this->hasMany(RestaurantOrderItem::class, 'order_id');
    }
}
