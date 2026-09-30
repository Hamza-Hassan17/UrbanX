<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreRideDriverWatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'passenger_id',
        'pickup_latitude',
        'pickup_longitude',
        'vehicle_type_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function passenger()
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
