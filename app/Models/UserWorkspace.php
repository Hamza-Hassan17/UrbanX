<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWorkspace extends Model
{
    protected $fillable = ['user_id', 'workspace'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
