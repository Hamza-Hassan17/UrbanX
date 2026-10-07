{{--
    Shared Ride/Order Queue component (Phase 2 of the admin workspace split).
    Used by both the Rides workspace (custom-rides/index.blade.php, taxi
    only) and the Delivery workspace (delivery/index.blade.php, food +
    parcel). Server-side filtering, presets and polling all live in
    queue-scripts.blade.php, included separately so a page can swap just
    the JS wiring (queueUrl/queuePresetUrl) while keeping this markup.

    Expected variables:
    - $queueTitle (string)            e.g. "Ride Queue" / "Orders Queue"
    - $itemPrefix (string)             e.g. "RIDE-" / "ORDER-"
    - $rides (iterable)                initial server-rendered rows, same
                                        shape queue-scripts.blade.php's JS
                                        renderer expects (id, time, pickup,
                                        dropoff, driver, passenger, phone,
                                        fare, status, queue, type_key, type_label)
    - $queuePresets (array)            saved filter presets for this queue
    - $queueTypeChips (array)          [value => label] for the type-chip
                                        row; empty array hides that row
                                        entirely (e.g. Rides workspace,
                                        taxi-only, nothing to switch between)
--}}
<div class="panel trips-container">
    <div class="trips-header" style="flex-wrap: wrap; row-gap: 10px;">
        <h3><i class="fas fa-list-check"></i> {{ $queueTitle }}</h3>
        @if (!empty($queueTypeChips))
            <div class="queue-tabs" id="queue-type-tabs">
                <button class="queue-tab active" data-type="all">All</button>
                @foreach ($queueTypeChips as $chipValue => $chipLabel)
                    <button class="queue-tab" data-type="{{ $chipValue }}">{{ $chipLabel }}</button>
                @endforeach
            </div>
        @endif
        <div class="queue-tabs" id="queue-tabs">
            <button class="queue-tab active" data-queue="all">All</button>
            <button class="queue-tab" data-queue="dispatch">Dispatch</button>
            <button class="queue-tab" data-queue="booked">Booked</button>
            <button class="queue-tab" data-queue="completed">Completed</button>
            <button class="queue-tab" data-queue="cancelled">Cancelled</button>
        </div>
        <span class="trip-count" id="queue-count">{{ count($rides) }} {{ $queueTitle }}</span>
    </div>
    <div class="queue-toolbar" id="queue-toolbar">
        <div class="queue-toolbar-group">
            <select id="qf-preset" class="queue-select" title="Presets">
                <option value="default">Default</option>
                @foreach ($queuePresets as $presetName => $presetFilters)
                    <option value="{{ $presetName }}">{{ $presetName }}</option>
                @endforeach
            </select>
            <button type="button" id="qf-save-preset" class="queue-tab">Save as preset</button>
            <label class="queue-field">From
                <input type="datetime-local" id="qf-from" class="queue-input">
            </label>
            <button type="button" id="qf-now" class="queue-tab active">Now</button>
            <span class="queue-sep">»</span>
            <select id="qf-window" class="queue-select" title="Window">
                <option value="1">1 HR</option>
                <option value="2">2 HR</option>
                <option value="4" selected>4 HR</option>
                <option value="8">8 HR</option>
                <option value="12">12 HR</option>
                <option value="24">24 HR</option>
                <option value="all">All</option>
            </select>
            <label class="queue-field">Until
                <input type="datetime-local" id="qf-until" class="queue-input">
            </label>
            <button type="button" id="qf-until-clear" class="queue-tab" title="Clear end time">—</button>
            <button type="button" id="qf-reset-default" class="queue-tab" title="Reset to default">Reset</button>
        </div>
        <div class="queue-toolbar-group">
            <button type="button" class="queue-tab" disabled title="Not available yet">✖ Recurring</button>
            <button type="button" class="queue-tab" disabled title="Not available yet">✖ Ticket</button>
            <button type="button" class="queue-tab" disabled title="Not available yet">✖ Groups</button>
            <button type="button" class="queue-tab" disabled title="Not available yet"><i class="fas fa-eye"></i></button>
        </div>
    </div>
    <div class="queue-table-wrap">
        <table class="queue-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>{{ Str::singular($queueTitle) }}</th>
                    <th>Type</th>
                    <th>Pickup</th>
                    <th>Dropoff</th>
                    <th>Driver</th>
                    <th>Passenger</th>
                    <th>Phone</th>
                    <th>Fare</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="queue-table-body">
                @forelse($rides as $ride)
                    <tr data-queue="{{ $ride['queue'] }}" data-type="{{ $ride['type_key'] }}">
                        <td class="muted-cell">{{ $ride['time'] }}</td>
                        <td>{{ $itemPrefix }}{{ $ride['id'] }}</td>
                        <td>
                            <span class="trip-type-badge trip-type-{{ $ride['type_key'] }}">
                                {{ $ride['type_label'] }}
                            </span>
                        </td>
                        <td class="muted-cell">{{ $ride['pickup'] }}</td>
                        <td class="muted-cell">{{ $ride['dropoff'] }}</td>
                        <td>{{ $ride['driver'] ?? 'Not assigned' }}</td>
                        <td>
                            @if (!empty($ride['passenger_id']) && $ride['passenger'] && auth()->user()->can('view user'))
                                <a href="{{ route('dashboard.customers.show', $ride['passenger_id']) }}">{{ $ride['passenger'] }}</a>
                            @else
                                {{ $ride['passenger'] ?? '--' }}
                            @endif
                        </td>
                        <td class="muted-cell">{{ $ride['phone'] ?? '--' }}</td>
                        <td>Rs {{ number_format($ride['fare']) }}</td>
                        <td>
                            @php
                                $badgeClass = match($ride['status']) {
                                    'completed' => 'status-completed',
                                    'cancelled' => 'status-cancelled',
                                    'requested' => 'status-pending',
                                    default => 'status-active',
                                };
                            @endphp
                            <span class="trip-status {{ $badgeClass }}">{{ ucwords(str_replace('_', ' ', $ride['status'])) }}</span>
                        </td>
                        <td>
                            @if(in_array($ride['queue'], ['dispatch', 'booked']) && isset($queueEditable) && $queueEditable)
                                <button type="button" class="queue-edit-btn" data-id="{{ $ride['id'] }}" data-status="{{ $ride['status'] }}" data-pickup="{{ $ride['pickup'] }}" data-dropoff="{{ $ride['dropoff'] }}" data-type="{{ $ride['type_key'] }}" data-queue="{{ $ride['queue'] }}" data-fare="{{ $ride['fare'] }}">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                            @else
                                --
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr id="queue-empty-row"><td colspan="11" class="queue-empty">No items in the queue</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
