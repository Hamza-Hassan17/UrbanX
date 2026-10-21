<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'ride_id',
        'driver_id',
        'proposed_price',
        'eta_minutes',
        'note',
        'status',
        'offered_at',
        'accepted_at',
        'expires_at',
    ];

    protected $casts = [
        'offered_at' => 'datetime',
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    /**
     * Offers that still count for "never offer the same job to the same
     * driver twice" -- a customer retry (Batch 3 Part 6) supersedes the
     * earlier ones.
     */
    public function scopeCurrent($query)
    {
        return $query->whereNull('ride_offers.superseded_at');
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
