<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverSelfie extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'picture',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
