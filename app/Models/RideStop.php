<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'ride_id',
        'sequence',
        'latitude',
        'longitude',
        'address',
        'arrived_at',
    ];

    protected $casts = [
        'arrived_at' => 'datetime',
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }
}
