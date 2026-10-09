<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverPayout extends Model
{
    protected $fillable = [
        'driver_id',
        'period_start',
        'period_end',
        'amount',
        'method',
        'reference',
        'paid_by',
        'paid_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
