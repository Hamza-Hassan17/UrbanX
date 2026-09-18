<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideLocationPing extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'ride_id',
        'driver_id',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'created_at' => 'datetime',
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
