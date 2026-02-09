<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideDriverLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ride_id',
        'driver_id',
        'action',
        'note',
    ];
}
