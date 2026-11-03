<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EatsSupportInfo extends Model
{
    protected $table = 'eats_support_info';

    protected $fillable = [
        'phone',
        'email',
        'address',
        'about',
        'facebook_url',
        'instagram_url',
        'twitter_url',
    ];
}
