<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantVerification extends Model
{
    protected $fillable = [
        'restaurant_id',
        'owner_cnic_front',
        'owner_cnic_back',
        'ntn_number',
        'food_authority_license',
        'bank_account_title',
        'bank_iban',
        'logo',
        'storefront_photo',
        'status',
        'rejection_reason',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
