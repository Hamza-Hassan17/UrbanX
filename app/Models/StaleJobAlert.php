<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaleJobAlert extends Model
{
    protected $fillable = [
        'type',
        'subject_type',
        'subject_id',
        'workspace',
        'message',
        'status',
        'resolved_by',
        'resolution_note',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function subject()
    {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
    }
}
