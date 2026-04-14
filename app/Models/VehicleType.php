<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'base_fare',
        'first_km_price',
        'other_km_price',
        'seats',
        'is_delivery',
        'is_active',
    ];
}
