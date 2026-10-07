<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermsAndCondition extends Model
{
    protected $fillable = ['content', 'created_by'];

    /**
     * The currently published version -- the latest row. Publishing a new
     * version is always an insert (see the migration), never an update, so
     * "latest by id" is always correct.
     */
    public static function current(): ?self
    {
        return static::latest('id')->first();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
