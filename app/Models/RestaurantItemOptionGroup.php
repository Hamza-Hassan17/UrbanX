<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantItemOptionGroup extends Model
{
    protected $fillable = [
        'restaurant_item_id',
        'name',
        'is_required',
        'selection_type',
        'min_select',
        'max_select',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'min_select' => 'integer',
        'max_select' => 'integer',
        'sort_order' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(RestaurantItem::class, 'restaurant_item_id');
    }

    public function options()
    {
        return $this->hasMany(RestaurantItemOption::class, 'option_group_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Fewest choices a customer must make in this group. A required group
     * always needs at least one; an optional group needs none.
     */
    public function effectiveMin(): int
    {
        if (!$this->is_required) {
            return 0;
        }

        return max(1, (int) $this->min_select);
    }

    /**
     * Most choices allowed; null means no limit.
     */
    public function effectiveMax(): ?int
    {
        if ($this->selection_type === 'single') {
            return 1;
        }

        return $this->max_select;
    }
}
