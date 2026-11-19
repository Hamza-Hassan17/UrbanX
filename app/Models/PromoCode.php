<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'discount_percentage',
        'valid_from',
        'valid_until',
        'usage_limit_per_user',
        'usage_limit',
        'is_active',
        'service',
        'user_id',
        'created_by',
    ];
}
