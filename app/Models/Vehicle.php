<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    public function carBrand()
    {
        return $this->belongsTo(CarBrand::class, 'car_brand_id');
    }
}
