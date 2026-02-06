<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'requested_at' => 'datetime',
    ];


    protected $fillable = [
        'passenger_id',
        'driver_id',
        'vehicle_type_id',
        'promo_code_id',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_latitude',
        'dropoff_longitude',
        'distance_km',
        'duration_minutes',
        'subtotal',
        'discount_amount',
        'extra_charges',
        'total_fare',
        'status',
        'requested_at',
        'accepted_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
    ];

    public function rideOffers()
    {
        return $this->hasMany(RideOffer::class, 'ride_id');
    }

    public function passenger()
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }
}
