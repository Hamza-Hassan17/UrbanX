<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverOnlineSession extends Model
{
    protected $fillable = [
        'driver_id',
        'started_at',
        'ended_at',
        'ended_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
