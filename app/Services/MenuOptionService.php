<?php

namespace App\Services;

use App\Models\RestaurantItem;
use InvalidArgumentException;

/**
 * Batch 3 Part 2 -- validates a customer's chosen add-ons for one menu item
 * against the item's option groups (required/optional, single/multiple,
 * min/max, availability) and prices the line server-side. Used by
 * addToCart (when the choice is made) and placeOrder (re-checked against
 * the live menu, since an option can go out of stock after it was carted).
 *
 * Throws InvalidArgumentException with a customer-facing message on any
 * rule break; callers return it as the API error message.
 */
class MenuOptionService
{
    /**
     * @return array{unit_price: float, base_price: float, selected_options: array, signature: ?string}
     */
    public static function priceLine(RestaurantItem $item, array $optionIds): array
    {
        if ((string) $item->is_available !== '1' || $item->is_active !== 'active') {
            throw new InvalidArgumentException("{$item->name} is currently unavailable.");
        }

        $optionIds = array_values(array_unique(array_map('intval', array_filter($optionIds, fn ($id) => $id !== null && $id !== ''))));
        $groups = $item->relationLoaded('optionGroups') ? $item->optionGroups : $item->optionGroups()->with('options')->get();
        $groups->loadMissing('options');

        $basePrice = $item->basePrice();

        // No groups: the item behaves exactly as before Batch 3.
        if ($groups->isEmpty()) {
            if (!empty($optionIds)) {
                throw new InvalidArgumentException("{$item->name} has no add-ons to choose from.");
            }

            return [
                'unit_price' => round($basePrice, 2),
                'base_price' => round($basePrice, 2),
                'selected_options' => [],
                'signature' => null,
            ];
        }

        $optionIndex = [];
        foreach ($groups as $group) {
            foreach ($group->options as $option) {
                $optionIndex[$option->id] = [$group, $option];
            }
        }

        foreach ($optionIds as $id) {
            if (!isset($optionIndex[$id])) {
                throw new InvalidArgumentException("One of the selected add-ons does not belong to {$item->name}. Please reopen the item and choose again.");
            }
        }

        $selected = [];
        $adjustment = 0.0;

        foreach ($groups as $group) {
            $chosen = $group->options->filter(fn ($o) => in_array($o->id, $optionIds, true));
            $count = $chosen->count();
            $min = $group->effectiveMin();
            $max = $group->effectiveMax();

            if ($count < $min) {
                throw new InvalidArgumentException($min === 1
                    ? "Please choose an option for \"{$group->name}\"."
                    : "Please choose at least {$min} options for \"{$group->name}\".");
            }
            if ($max !== null && $count > $max) {
                throw new InvalidArgumentException($max === 1
                    ? "You can choose only one option for \"{$group->name}\"."
                    : "You can choose up to {$max} options for \"{$group->name}\".");
            }

            foreach ($chosen as $option) {
                if (!$option->is_available) {
                    throw new InvalidArgumentException("\"{$option->name}\" is currently unavailable. Please choose another option.");
                }
                $adjustment += (float) $option->price_adjustment;
                $selected[] = [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'option_id' => $option->id,
                    'option_name' => $option->name,
                    'price_adjustment' => round((float) $option->price_adjustment, 2),
                ];
            }
        }

        $sortedIds = $optionIds;
        sort($sortedIds);

        return [
            'unit_price' => round(max(0, $basePrice + $adjustment), 2),
            'base_price' => round($basePrice, 2),
            'selected_options' => $selected,
            'signature' => empty($sortedIds) ? null : implode(',', $sortedIds),
        ];
    }

    /**
     * Validation rules for creating/updating an option group, shared by the
     * Partner API and the admin restaurant pages.
     */
    public static function groupRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'is_required' => 'required|boolean',
            'selection_type' => 'required|in:single,multiple',
            'min_select' => 'nullable|integer|min:0',
            'max_select' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer',
        ];
    }

    public static function optionRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price_adjustment' => 'nullable|numeric|min:-1000000|max:1000000',
            'is_available' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ];
    }

    /**
     * Normalised group attributes from validated input, or an error string
     * if min/max contradict each other.
     */
    public static function groupAttributes(array $input): array|string
    {
        $single = $input['selection_type'] === 'single';
        $required = filter_var($input['is_required'], FILTER_VALIDATE_BOOLEAN);
        $min = $single ? ($required ? 1 : 0) : (int) ($input['min_select'] ?? ($required ? 1 : 0));
        $max = $single ? 1 : (isset($input['max_select']) && $input['max_select'] !== '' ? (int) $input['max_select'] : null);

        if (!$required) {
            $min = 0;
        } elseif ($min < 1) {
            $min = 1;
        }

        if ($max !== null && $min > $max) {
            return 'Minimum selections cannot be more than maximum selections.';
        }

        return [
            'name' => $input['name'],
            'is_required' => $required,
            'selection_type' => $input['selection_type'],
            'min_select' => $min,
            'max_select' => $max,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ];
    }

    /**
     * Option IDs recorded on a cart line, for re-checking at order time.
     */
    public static function optionIdsFromSnapshot(?array $selected): array
    {
        return array_map(fn ($o) => (int) $o['option_id'], $selected ?? []);
    }
}
