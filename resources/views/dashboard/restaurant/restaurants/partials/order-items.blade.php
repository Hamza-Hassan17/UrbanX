{{-- Order items with the add-ons chosen at order time (Batch 3 Part 2). --}}
@foreach ($items as $orderItem)
    <div class="mb-1">
        <strong>{{ $orderItem->quantity }}×</strong>
        {{ $orderItem->item_name ?? ($orderItem->restaurantItem->name ?? __('Item') . ' #' . $orderItem->restaurant_item_id) }}
        <span class="text-muted small">({{ \App\Helpers\Helper::formatCurrency($orderItem->price_per_item) }})</span>
        @if (!empty($orderItem->selected_options))
            <div class="small text-muted ps-3">
                @foreach ($orderItem->selected_options as $option)
                    <div>
                        {{ $option['group_name'] ?? '' }}: {{ $option['option_name'] ?? '' }}
                        @if ((float) ($option['price_adjustment'] ?? 0) != 0)
                            ({{ (float) $option['price_adjustment'] > 0 ? '+' : '' }}{{ \App\Helpers\Helper::formatCurrency($option['price_adjustment']) }})
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endforeach
