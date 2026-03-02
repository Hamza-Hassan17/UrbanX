<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'discount_amount',
        'discount_type',
        'minimum_purchase',
        'per_user_limit',
        'expires_at',
        'description',
        'is_active',
    ];
}
