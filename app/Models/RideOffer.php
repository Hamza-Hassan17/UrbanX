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

    public function ride()
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
