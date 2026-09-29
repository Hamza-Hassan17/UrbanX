<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'table_name',
        'table_id',
        'page',
        'is_popup',
    ];

    protected $casts = [
        'is_popup' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
