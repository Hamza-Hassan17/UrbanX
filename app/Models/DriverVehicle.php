<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverVehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'vehicle_type_id',
        'vehicle_name',
        'vehicle_make',
        'vehicle_model',
        'vehicle_color',
        'vehicle_year',
        'vehicle_plate_number',
        'vehicle_images',
        'registration_paper',
        'vehicle_video',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }
}
