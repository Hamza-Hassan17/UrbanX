<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $fillable = ['user_id', 'label', 'address', 'details', 'latitude', 'longitude', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];
}
