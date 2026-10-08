<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderCashLedger extends Model
{
    protected $fillable = [
        'rider_id',
        'entry_type',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'method',
        'note',
        'recorded_by',
    ];

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
