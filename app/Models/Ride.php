<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    // Fallback grace period/rate used only if an admin hasn't saved
    // system settings yet (see waitGraceMinutes()/waitPenaltyPerMinute()).
    const WAIT_GRACE_MINUTES = 5;
    const WAIT_PENALTY_PER_MINUTE = 9;

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'requested_at' => 'datetime',
        'accepted_at'  => 'datetime',
        'arrived_at'   => 'datetime',
        'scheduled_pickup_at' => 'datetime',
        'fare_breakdown' => 'array',
        'delivery_code_locked' => 'boolean',
        'delivery_override_at' => 'datetime',
        'flagged_for_review' => 'boolean',
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
        'distance_charged_km',
        'duration_minutes',
        'subtotal',
        'discount_amount',
        'extra_charges',
        'wait_penalty',
        'total_fare',
        'status',
        'ride_type',
        'requested_at',
        'scheduled_pickup_at',
        'accepted_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
        'status_updated_by',
        'status_updated_by_role',
        'created_by',
        'fare_breakdown',
        'sender_name',
        'sender_phone',
        'receiver_name',
        'receiver_phone',
        'package_type',
        'package_size',
        'parcel_notes',
        'delivery_fee_paid_by',
        'payment_method',
        'payment_status',
        'delivery_code',
        'delivery_code_attempts',
        'delivery_code_locked',
        'delivery_override_by',
        'delivery_override_reason',
        'delivery_override_at',
        'receiver_unreachable_photo',
        'flagged_for_review',
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

    public function stops()
    {
        return $this->hasMany(RideStop::class)->orderBy('sequence');
    }

    public function statusUpdatedBy()
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public static function waitGraceMinutes(): int
    {
        return (int) (SystemSetting::first()->wait_grace_minutes ?? self::WAIT_GRACE_MINUTES);
    }

    public static function waitPenaltyPerMinute(): float
    {
        return (float) (SystemSetting::first()->wait_penalty_per_minute ?? self::WAIT_PENALTY_PER_MINUTE);
    }

    /**
     * Wait-time penalty accrued since the driver arrived, as of a given
     * moment (defaults to now). Full extra minutes past the admin-configured
     * grace period are charged; a partial minute is not counted.
     */
    public function calculateWaitPenalty(?\Carbon\Carbon $asOf = null): float
    {
        if (!$this->arrived_at) {
            return 0;
        }

        $asOf = $asOf ?: now();
        $elapsedMinutes = floor($this->arrived_at->diffInSeconds($asOf) / 60);
        $lateMinutes = $elapsedMinutes - self::waitGraceMinutes();

        return $lateMinutes > 0 ? $lateMinutes * self::waitPenaltyPerMinute() : 0;
    }
}
