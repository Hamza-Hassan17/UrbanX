<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $guarded = ['id'];

    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    public function timezone()
    {
        return $this->belongsTo(Timezone::class);
    }
}
