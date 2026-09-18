<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideAnomaly extends Model
{
    use HasFactory;

    protected $fillable = [
        'ride_id',
        'driver_id',
        'type',
        'details',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at');
    }
}
