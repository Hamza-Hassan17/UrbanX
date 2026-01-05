<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideExtraCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'ride_id',
        'charge_type',
        'amount',
    ];
}
