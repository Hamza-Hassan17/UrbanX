<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantItem;
use App\Models\RestaurantItemOption;
use App\Models\RestaurantItemOptionGroup;
use App\Services\MenuOptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batch 3 Part 2 -- Partner app: manage add-on option groups/options on the
 * restaurant's own items, and quick out-of-stock toggles for items and
 * options. Every lookup is scoped to the logged-in owner's restaurant.
 */
class MenuOptionController extends Controller
{
    private function restaurant(Request $request): ?Restaurant
    {
        return Restaurant::where('user_id', $request->user()->id)->first();
    }

    private function ownedItem(Request $request, $itemId): ?RestaurantItem
    {
        $restaurant = $this->restaurant($request);

        return $restaurant
            ? RestaurantItem::where('restaurant_id', $restaurant->id)->where('id', $itemId)->first()
            : null;
    }

    private function ownedGroup(Request $request, $groupId): ?RestaurantItemOptionGroup
    {
        $restaurant = $this->restaurant($request);

        return $restaurant
            ? RestaurantItemOptionGroup::where('id', $groupId)
                ->whereHas('item', fn ($q) => $q->where('restaurant_id', $restaurant->id))
                ->first()
            : null;
    }

    private function ownedOption(Request $request, $optionId): ?RestaurantItemOption
    {
        $restaurant = $this->restaurant($request);

        return $restaurant
            ? RestaurantItemOption::where('id', $optionId)
                ->whereHas('group.item', fn ($q) => $q->where('restaurant_id', $restaurant->id))
                ->first()
            : null;
    }

    private function notFound(string $what)
    {
        return response()->json(['message' => "{$what} not found"], Response::HTTP_NOT_FOUND);
    }

    private function validationFailed($validator)
    {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], Response::HTTP_BAD_REQUEST);
    }

    public function index(Request $request, $item_id)
    {
        $item = $this->ownedItem($request, $item_id);
        if (!$item) {
            return $this->notFound('Menu item');
        }

        return response()->json([
            'message' => 'Option Groups',
            'item_id' => $item->id,
            'option_groups' => $item->optionGroups()->with('options')->get(),
        ], Response::HTTP_OK);
    }

    public function storeGroup(Request $request, $item_id)
    {
        $item = $this->ownedItem($request, $item_id);
        if (!$item) {
            return $this->notFound('Menu item');
        }

        $validator = Validator::make($request->all(), MenuOptionService::groupRules() + [
            'options' => 'nullable|array',
            'options.*.name' => 'required_with:options|string|max:255',
            'options.*.price_adjustment' => 'nullable|numeric',
            'options.*.is_available' => 'nullable|boolean',
            'options.*.sort_order' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $attributes = MenuOptionService::groupAttributes($request->all());
        if (is_string($attributes)) {
            return response()->json(['message' => $attributes], Response::HTTP_BAD_REQUEST);
        }

        try {
            $group = $item->optionGroups()->create($attributes);
            foreach ($request->input('options', []) as $index => $option) {
                $group->options()->create([
                    'name' => $option['name'],
                    'price_adjustment' => $option['price_adjustment'] ?? 0,
                    'is_available' => filter_var($option['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'sort_order' => $option['sort_order'] ?? $index,
                ]);
            }

            return response()->json([
                'message' => 'Option group added successfully',
                'option_group' => $group->load('options'),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Add Option Group failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateGroup(Request $request, $group_id)
    {
        $group = $this->ownedGroup($request, $group_id);
        if (!$group) {
            return $this->notFound('Option group');
        }

        $validator = Validator::make($request->all(), MenuOptionService::groupRules());
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $attributes = MenuOptionService::groupAttributes($request->all());
        if (is_string($attributes)) {
            return response()->json(['message' => $attributes], Response::HTTP_BAD_REQUEST);
        }

        $group->update($attributes);

        return response()->json([
            'message' => 'Option group updated successfully',
            'option_group' => $group->load('options'),
        ], Response::HTTP_OK);
    }

    public function destroyGroup(Request $request, $group_id)
    {
        $group = $this->ownedGroup($request, $group_id);
        if (!$group) {
            return $this->notFound('Option group');
        }

        // Past orders keep their own snapshot of chosen options, so
        // removing a group never changes order history.
        $group->delete();

        return response()->json(['message' => 'Option group deleted successfully'], Response::HTTP_OK);
    }

    public function storeOption(Request $request, $group_id)
    {
        $group = $this->ownedGroup($request, $group_id);
        if (!$group) {
            return $this->notFound('Option group');
        }

        $validator = Validator::make($request->all(), MenuOptionService::optionRules());
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $option = $group->options()->create([
            'name' => $request->name,
            'price_adjustment' => $request->input('price_adjustment', 0),
            'is_available' => $request->boolean('is_available', true),
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return response()->json([
            'message' => 'Option added successfully',
            'option' => $option,
        ], Response::HTTP_OK);
    }

    public function updateOption(Request $request, $option_id)
    {
        $option = $this->ownedOption($request, $option_id);
        if (!$option) {
            return $this->notFound('Option');
        }

        $validator = Validator::make($request->all(), MenuOptionService::optionRules());
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $option->update([
            'name' => $request->name,
            'price_adjustment' => $request->input('price_adjustment', 0),
            'is_available' => $request->boolean('is_available', (bool) $option->is_available),
            'sort_order' => $request->input('sort_order', $option->sort_order),
        ]);

        return response()->json([
            'message' => 'Option updated successfully',
            'option' => $option,
        ], Response::HTTP_OK);
    }

    public function destroyOption(Request $request, $option_id)
    {
        $option = $this->ownedOption($request, $option_id);
        if (!$option) {
            return $this->notFound('Option');
        }

        $option->delete();

        return response()->json(['message' => 'Option deleted successfully'], Response::HTTP_OK);
    }

    /**
     * Out-of-stock toggle. Send is_available (true/false) to set it, or
     * nothing to flip the current value.
     */
    public function toggleItemAvailability(Request $request, $item_id)
    {
        $item = $this->ownedItem($request, $item_id);
        if (!$item) {
            return $this->notFound('Menu item');
        }

        $validator = Validator::make($request->all(), ['is_available' => 'nullable|boolean']);
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $available = $request->has('is_available')
            ? $request->boolean('is_available')
            : (string) $item->is_available !== '1';
        $item->is_available = $available ? '1' : '0';
        $item->save();

        return response()->json([
            'message' => $available ? 'Item marked as available' : 'Item marked as out of stock',
            'item_id' => $item->id,
            'is_available' => $item->is_available,
        ], Response::HTTP_OK);
    }

    public function toggleOptionAvailability(Request $request, $option_id)
    {
        $option = $this->ownedOption($request, $option_id);
        if (!$option) {
            return $this->notFound('Option');
        }

        $validator = Validator::make($request->all(), ['is_available' => 'nullable|boolean']);
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $option->is_available = $request->has('is_available')
            ? $request->boolean('is_available')
            : !$option->is_available;
        $option->save();

        return response()->json([
            'message' => $option->is_available ? 'Option marked as available' : 'Option marked as out of stock',
            'option_id' => $option->id,
            'is_available' => $option->is_available,
        ], Response::HTTP_OK);
    }
}
