<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPreference extends Model
{
    protected $fillable = ['user_id', 'language', 'push_offers', 'email_offers'];

    protected $casts = ['push_offers' => 'boolean', 'email_offers' => 'boolean'];
}
