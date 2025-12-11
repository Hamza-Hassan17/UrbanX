<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'vehicle_id',
        'name',
        'email',
        'phone',
        'rent_type',
        'with_driver',
        'start_time',
        'end_time',
        'pickup_location',
        'dropoff_location',
        'subtotal',
        'discount',
        'tax',
        'total_amount',
        'notes',
        'status',
        'cancel_reason',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            $lastBooking = Booking::orderBy('id', 'desc')->first();
            $nextNumber = $lastBooking ? $lastBooking->id + 1 : 1;
            $padded = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            $booking->booking_id = 'BK-' . $padded;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class, 'booking_id');
    }
}
