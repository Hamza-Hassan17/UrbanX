<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'review_by',
        'rating',
        'comments',
    ];
}
