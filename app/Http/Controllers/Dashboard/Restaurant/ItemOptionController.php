<?php

namespace App\Http\Controllers\Dashboard\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\RestaurantItem;
use App\Models\RestaurantItemOption;
use App\Models\RestaurantItemOptionGroup;
use App\Services\MenuOptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Batch 3 Part 2 -- admin view/management of a menu item's add-on option
 * groups and options (Delivery workspace, under Restaurants).
 */
class ItemOptionController extends Controller
{
    public function index(string $itemId)
    {
        $this->authorize('view restaurant');

        $item = RestaurantItem::with(['restaurant:id,name', 'optionGroups.options'])->findOrFail($itemId);

        return view('dashboard.restaurant.restaurants.item-options', compact('item'));
    }

    public function storeGroup(Request $request, string $itemId)
    {
        $this->authorize('update restaurant');
        $item = RestaurantItem::findOrFail($itemId);

        $validator = Validator::make($request->all(), MenuOptionService::groupRules());
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Validation Error!');
        }

        $attributes = MenuOptionService::groupAttributes($request->all());
        if (is_string($attributes)) {
            return redirect()->back()->withInput()->with('error', $attributes);
        }

        $item->optionGroups()->create($attributes);

        return redirect()->back()->with('success', 'Option group added');
    }

    public function updateGroup(Request $request, string $groupId)
    {
        $this->authorize('update restaurant');
        $group = RestaurantItemOptionGroup::findOrFail($groupId);

        $validator = Validator::make($request->all(), MenuOptionService::groupRules());
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Validation Error!');
        }

        $attributes = MenuOptionService::groupAttributes($request->all());
        if (is_string($attributes)) {
            return redirect()->back()->withInput()->with('error', $attributes);
        }

        $group->update($attributes);

        return redirect()->back()->with('success', 'Option group updated');
    }

    public function destroyGroup(string $groupId)
    {
        $this->authorize('update restaurant');
        RestaurantItemOptionGroup::findOrFail($groupId)->delete();

        return redirect()->back()->with('success', 'Option group deleted');
    }

    public function storeOption(Request $request, string $groupId)
    {
        $this->authorize('update restaurant');
        $group = RestaurantItemOptionGroup::findOrFail($groupId);

        $validator = Validator::make($request->all(), MenuOptionService::optionRules());
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Validation Error!');
        }

        $group->options()->create([
            'name' => $request->name,
            'price_adjustment' => $request->input('price_adjustment', 0) ?: 0,
            'is_available' => $request->boolean('is_available', true),
            'sort_order' => $request->input('sort_order', 0) ?: 0,
        ]);

        return redirect()->back()->with('success', 'Option added');
    }

    public function updateOption(Request $request, string $optionId)
    {
        $this->authorize('update restaurant');
        $option = RestaurantItemOption::findOrFail($optionId);

        $validator = Validator::make($request->all(), MenuOptionService::optionRules());
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Validation Error!');
        }

        $option->update([
            'name' => $request->name,
            'price_adjustment' => $request->input('price_adjustment', 0) ?: 0,
            'is_available' => $request->boolean('is_available'),
            'sort_order' => $request->input('sort_order', 0) ?: 0,
        ]);

        return redirect()->back()->with('success', 'Option updated');
    }

    public function destroyOption(string $optionId)
    {
        $this->authorize('update restaurant');
        RestaurantItemOption::findOrFail($optionId)->delete();

        return redirect()->back()->with('success', 'Option deleted');
    }

    public function toggleOption(string $optionId)
    {
        $this->authorize('update restaurant');
        $option = RestaurantItemOption::findOrFail($optionId);
        $option->is_available = !$option->is_available;
        $option->save();

        return redirect()->back()->with('success', $option->is_available ? 'Option marked as available' : 'Option marked as out of stock');
    }

    public function toggleItem(string $itemId)
    {
        $this->authorize('update restaurant');
        $item = RestaurantItem::findOrFail($itemId);
        $item->is_available = (string) $item->is_available === '1' ? '0' : '1';
        $item->save();

        return redirect()->back()->with('success', $item->is_available === '1' ? 'Item marked as available' : 'Item marked as out of stock');
    }
}
