<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'admin_id',
        'action',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Records a change, keeping only the fields whose value actually
     * changed between $old and $new (so every log row is a real diff,
     * not a full dump of every field on every save).
     */
    public static function record(string $action, array $old, array $new, ?string $subjectType = null, ?int $subjectId = null): void
    {
        $changedOld = [];
        $changedNew = [];

        foreach ($new as $key => $value) {
            $oldValue = $old[$key] ?? null;
            if ((string) $oldValue !== (string) $value) {
                $changedOld[$key] = $oldValue;
                $changedNew[$key] = $value;
            }
        }

        if (empty($changedNew)) {
            return;
        }

        static::create([
            'admin_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'old_values' => $changedOld,
            'new_values' => $changedNew,
        ]);
    }
}
