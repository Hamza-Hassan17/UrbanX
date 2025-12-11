<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'booking_id',
        'trx_id',
        'amount',
        'payment_method',
        'payment_status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            $lastTrx = Transaction::orderBy('id', 'desc')->first();
            $nextNumber = $lastTrx ? $lastTrx->id + 1 : 1;
            $padded = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            $transaction->trx_id = 'TRX-' . $padded;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
