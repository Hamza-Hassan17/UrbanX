@extends('layouts.master')

@section('title', __('Custom Rides'))

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --accent: #eab308;
            --accent-dark: #a16207;
            --dark: #1f2937;
            --gray: #6b7280;
            --border: #e3e1d3;
            --surface: #ffffff;
            --surface-alt: #f6f5ee;
            --page-bg: #ebe9dd;
            --card-shadow: 0 2px 6px -1px rgba(31, 41, 55, 0.08), 0 1px 3px -1px rgba(31, 41, 55, 0.06);
        }

        .dashboard-container,
        .dashboard-container .content-wrapper {
            color: var(--dark);
        }

        .dashboard-container {
            background: var(--page-bg);
            padding: 6px 2px 2px;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--gray);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: var(--primary);
            width: 16px;
        }

        @keyframes anomaly-pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.6); }
            70% { box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
        }

        /* Top row: form | counts | map */
        .dispatch-toprow {
            display: grid;
            grid-template-columns: 300px 190px 1fr;
            gap: 14px;
            align-items: stretch;
            min-height: 480px;
        }

        .form-panel {
            padding: 14px;
            overflow-y: auto;
        }

        /* Compact driver strip */
        .driver-card {
            background: var(--surface-alt);
            border-radius: 10px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }

        .driver-header {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }

        .driver-avatar {
            width: 38px;
            height: 38px;
            border-radius: 9px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            position: relative;
            flex-shrink: 0;
        }

        .driver-status {
            position: absolute;
            bottom: -3px;
            right: -3px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
        }

        .status-online {
            background-color: var(--secondary);
        }

        .status-offline {
            background-color: var(--danger);
        }

        .status-busy {
            background-color: var(--warning);
        }

        .driver-info h3 {
            font-size: 13px;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .driver-meta {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .driver-meta span {
            font-size: 11px;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .driver-meta i {
            width: 12px;
            color: var(--primary);
            font-size: 10px;
        }

        .driver-actions {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .btn {
            padding: 7px 10px;
            border-radius: 7px;
            border: none;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-outline {
            background: var(--surface);
            color: var(--primary);
            border: 1.5px solid var(--border);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            background: var(--surface-alt);
        }

        .shortcut-hint {
            display: inline-block;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.03em;
            color: var(--accent-dark);
            background: #fef3c7;
            border: 1px solid var(--accent);
            border-radius: 4px;
            padding: 1px 5px;
            margin-left: 5px;
            vertical-align: middle;
        }

        /* Dense field grid */
        .field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 3px;
            position: relative;
        }

        .field-full {
            grid-column: 1 / -1;
        }

        .field label {
            display: flex;
            align-items: center;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--gray);
        }

        .field label i {
            color: var(--primary);
            margin-right: 4px;
            width: 12px;
            font-size: 10px;
        }

        .form-control {
            width: 100%;
            padding: 8px 10px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            font-size: 13px;
            transition: all 0.2s;
            background: var(--surface-alt);
            color: var(--dark);
        }

        .form-control::placeholder {
            color: #a8a596;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
            background: var(--surface);
        }

        /* Autocomplete Dropdown */
        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 220px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: var(--card-shadow);
            display: none;
        }

        .autocomplete-item {
            padding: 9px 12px;
            cursor: pointer;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }

        .autocomplete-item:hover {
            background-color: var(--surface-alt);
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .location-icon {
            color: var(--primary);
            font-size: 14px;
            width: 18px;
            text-align: center;
        }

        .location-details {
            flex: 1;
            min-width: 0;
        }

        .location-name {
            font-weight: 600;
            font-size: 13px;
            color: var(--dark);
            margin-bottom: 1px;
        }

        .location-address {
            font-size: 11px;
            color: var(--gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Loading spinner */
        .loading-spinner {
            text-align: center;
            padding: 10px;
            color: var(--gray);
            font-size: 12px;
        }

        .loading-spinner i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .save-job-btn {
            width: 100%;
            margin-top: 4px;
            padding: 11px;
            font-size: 12px;
        }

        /* Counts panel */
        .counts-panel {
            padding: 14px;
        }

        .count-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 7px 2px;
            border-bottom: 1px dashed var(--border);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--gray);
        }

        .count-row:last-of-type {
            border-bottom: none;
        }

        .count-row strong {
            font-size: 16px;
            font-weight: 800;
            color: var(--dark);
        }

        .count-row.count-available strong { color: var(--secondary); }
        .count-row.count-busy strong { color: var(--warning); }
        .count-row.count-dispatch strong { color: var(--primary); }
        .count-row.count-booked strong { color: #7c3aed; }
        .count-row.count-completed strong { color: var(--secondary); }
        .count-row.count-cancelled strong { color: var(--danger); }

        .fare-box {
            margin-top: auto;
            background: linear-gradient(135deg, #fef9e7, #fde68a);
            border: 1px solid var(--accent);
            border-radius: 10px;
            padding: 12px;
            text-align: center;
        }

        .fare-box .fare-label {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: var(--accent-dark);
            text-transform: uppercase;
        }

        .fare-box .fare-value {
            font-size: 21px;
            font-weight: 800;
            color: #92400e;
            margin-top: 2px;
        }

        .fare-box .fare-sub {
            display: flex;
            justify-content: space-around;
            margin-top: 8px;
            font-size: 10px;
            font-weight: 700;
            color: var(--accent-dark);
            text-transform: uppercase;
        }

        /* Map panel */
        .map-panel {
            padding: 0;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* Was position:absolute; inset:0 before -- that made #map fill the whole
           panel edge-to-edge and completely cover this bar, which lived inside
           the same panel above it. Flex column + #map:flex:1 below fixes it:
           this bar takes its natural height, #map gets everything left over. */
        .live-tracking-bar {
            padding: 8px 10px;
            border-bottom: 1px solid var(--border, #e5e7eb);
            flex-shrink: 0;
        }

        #map {
            position: relative;
            width: 100%;
            flex: 1;
            z-index: 1;
        }

        /* Trip Queue */
        .trips-container {
            padding: 20px;
        }

        .trips-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }

        .trips-header h3 {
            font-size: 15px;
            font-weight: 800;
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .trip-count {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .queue-tabs {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .queue-tab {
            border: 1.5px solid var(--border);
            background: var(--surface-alt);
            color: var(--gray);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 6px 14px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .queue-tab:hover {
            border-color: var(--accent);
            color: var(--dark);
        }

        .queue-tab.active {
            background: var(--accent);
            border-color: var(--accent-dark);
            color: #422006;
        }

        .queue-table-wrap {
            overflow-x: auto;
        }

        table.queue-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .queue-table thead th {
            text-align: left;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--gray);
            padding: 9px 12px;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }

        .queue-table tbody td {
            padding: 9px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--dark);
            vertical-align: middle;
        }

        .queue-table tbody tr:hover {
            background: var(--surface-alt);
        }

        .queue-table .muted-cell {
            color: var(--gray);
            font-size: 12px;
        }

        .trip-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .status-active {
            background: rgba(37, 99, 235, 0.12);
            color: var(--primary);
        }

        .status-completed {
            background: rgba(16, 185, 129, 0.12);
            color: var(--secondary);
        }

        .status-pending {
            background: rgba(234, 179, 8, 0.18);
            color: var(--accent-dark);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.12);
            color: var(--danger);
        }

        .queue-empty {
            padding: 30px;
            text-align: center;
            color: var(--gray);
        }

        .trip-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .trip-type-ride {
            background: rgba(37, 99, 235, 0.12);
            color: var(--primary);
        }

        .trip-type-delivery {
            background: rgba(234, 88, 12, 0.14);
            color: #c2410c;
        }

        .queue-edit-btn {
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--primary);
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .queue-edit-btn:hover {
            background: var(--surface-alt);
        }

        .edit-ride-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .edit-ride-modal-overlay.open {
            display: flex;
        }

        .edit-ride-modal {
            background: var(--surface);
            border-radius: 12px;
            width: 100%;
            max-width: 360px;
            padding: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
        }

        .edit-ride-modal h4 {
            margin: 0 0 16px;
            font-size: 16px;
            font-weight: 800;
            color: var(--dark);
        }

        .edit-ride-modal .field {
            margin-bottom: 16px;
        }

        .edit-ride-modal .field label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--gray);
            margin-bottom: 6px;
        }

        .edit-ride-modal .field select {
            width: 100%;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: 13px;
        }

        .edit-ride-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .dispatch-toprow {
                grid-template-columns: 1fr;
                min-height: 0;
            }

            .form-panel,
            .counts-panel {
                max-height: none;
            }

            .map-panel {
                min-height: 380px;
            }
        }

        @media (max-width: 768px) {
            .field-grid {
                grid-template-columns: 1fr;
            }
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--surface);
            color: var(--dark);
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 2000;
            animation: slideIn 0.3s ease-out;
            border-left: 4px solid var(--primary);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Custom Rides') }}</li>
@endsection
@section('content')
    <div class="dashboard-container">

        <!-- Top row: booking form | live counts+fare | map -->
        <div class="dispatch-toprow">

            <!-- Booking Form Panel -->
            <section class="panel form-panel">
                <!-- Compact driver strip -->
                <div class="driver-card">
                    <div class="driver-header">
                        <div class="driver-avatar">
                            {{ strtoupper(substr($driver->name ?? 'NA', 0, 2)) }}
                            <div class="driver-status status-online"></div>
                        </div>
                        <div class="driver-info">
                            <h3>{{ $driver->name ?? 'Not Assigned' }}</h3>
                            <div class="driver-meta">
                                <span><i class="fas fa-id-badge"></i> ID: {{ $driver->id }}</span>
                                <span><i class="fas fa-car"></i>
                                    {{ $driver->driverVehicle ? $driver->driverVehicle->vehicle_name . ' ' . $driver->driverVehicle->vehicle_make : 'N/A' }}</span>
                            </div>

                            <input type="text" hidden value="{{ $driver->driverVehicle->vehicle_type_id }}"
                                name="vehicle_type_id" id="vehicle_type_id">
                        </div>
                    </div>
                    <div class="driver-actions">
                        <button class="btn btn-outline" id="message-driver" title="Message driver">
                            <i class="fas fa-comment"></i>
                        </button>
                    </div>
                    <input type="text" hidden value="{{ $driver->phone }}" name="driver_phone" id="driver_phone">
                    <input type="text" hidden value="{{ $driver->id }}" name="driver_id" id="driver_id">
                </div>

                <div class="section-title">
                    <i class="fas fa-route"></i>
                    <span>New Ride</span>
                </div>

                <div class="field-grid">
                    <div class="field field-full">
                        <label for="driverIdInput"><i class="fas fa-id-card"></i> Driver #ID <span class="shortcut-hint">F4</span></label>
                        <div style="display:flex; gap:6px;">
                            <input type="text" id="driverIdInput" name="driver_id_input" class="form-control"
                                placeholder="Driver ID" value="{{ $driver->id }}" style="flex:1;">
                            <button type="button" id="assignNearestDriverBtn" class="btn btn-outline"
                                title="Assign nearest available driver to the pickup location">
                                <i class="fas fa-location-crosshairs"></i> Nearest
                            </button>
                        </div>
                    </div>

                    <div class="field field-full">
                        <label for="pickup-location"><i class="fas fa-location-dot"></i> Pickup <span class="shortcut-hint">F2</span></label>
                        <input type="text" id="pickup-location" class="form-control"
                            placeholder="Start typing a location...">
                        <div class="autocomplete-dropdown" id="pickup-autocomplete"></div>
                    </div>

                    <div class="field field-full">
                        <label for="destination"><i class="fas fa-flag-checkered"></i> Destination <span class="shortcut-hint">F10</span></label>
                        <input type="text" id="destination" class="form-control"
                            placeholder="Start typing a destination...">
                        <div class="autocomplete-dropdown" id="destination-autocomplete"></div>
                    </div>

                    <div class="field">
                        <label for="passenger_name"><i class="fas fa-user"></i> Name</label>
                        <input type="text" id="passenger_name" name="passenger_name" class="form-control" placeholder="Passenger name">
                    </div>
                    <div class="field">
                        <label for="passenger_phone"><i class="fas fa-phone"></i> Phone</label>
                        <input type="text" id="passenger_phone" name="passenger_phone" class="form-control" placeholder="Phone">
                    </div>
                    <input type="text" hidden name="ride_distance" id="ride_distance">
                </div>

                <button class="btn btn-primary save-job-btn" id="assign-trip">
                    <i class="fas fa-paper-plane"></i> Save Job <span class="shortcut-hint">F1</span>
                </button>
            </section>

            <!-- Live Counts + Fare Panel -->
            <section class="panel counts-panel">
                <div class="section-title">
                    <i class="fas fa-chart-simple"></i>
                    <span>Live</span>
                </div>

                <div class="count-row count-available">
                    <span>Available</span>
                    <strong id="stat-available">{{ $driverAvailableCount }}</strong>
                </div>
                <div class="count-row count-busy">
                    <span>Busy</span>
                    <strong id="stat-busy">{{ $driverBusyCount }}</strong>
                </div>
                <div class="count-row count-dispatch">
                    <span>Dispatch</span>
                    <strong id="stat-dispatch">{{ $rideCounts['dispatch'] }}</strong>
                </div>
                <div class="count-row count-booked">
                    <span>Booked</span>
                    <strong id="stat-booked">{{ $rideCounts['booked'] }}</strong>
                </div>
                <div class="count-row count-completed">
                    <span>Completed</span>
                    <strong id="stat-completed">{{ $rideCounts['completed'] }}</strong>
                </div>
                <div class="count-row count-cancelled">
                    <span>Cancelled</span>
                    <strong id="stat-cancelled">{{ $rideCounts['cancelled'] }}</strong>
                </div>

                <div class="fare-box">
                    <div class="fare-label">Fare</div>
                    <div class="fare-value" id="fare-container">
                        <span id="original-fare"
                            style="text-decoration: line-through; color: #b45309; display: none; margin-right: 5px; font-size: 13px;"></span>
                        <span id="final-fare">Rs 0</span>
                        <sup id="boost-multiplier"
                            style="background: #f59e0b; font-size: 0.5em; color: white; display: none; padding: 3px 5px; border-radius: 50px; margin-left: 4px;">x1.0</sup>
                    </div>
                    <div class="fare-sub">
                        <span id="distance">0 km</span>
                        <span id="time">0 min</span>
                    </div>
                </div>
            </section>

            <!-- Map Panel -- pickup/destination pin-drop + route only. Live
                 driver/anomaly monitoring moved to its own page, see
                 dashboard.live-tracking.index. -->
            <section class="panel map-panel">
                <div id="map"></div>
            </section>
        </div>

        <!-- Ride Queue (full width) -->
        <div class="panel trips-container">
            <div class="trips-header" style="flex-wrap: wrap; row-gap: 10px;">
                <h3><i class="fas fa-list-check"></i> Ride Queue</h3>
                <div class="queue-tabs" id="queue-type-tabs">
                    <button class="queue-tab active" data-type="all">All Types</button>
                    <button class="queue-tab" data-type="ride">🚕 Taxi</button>
                    <button class="queue-tab" data-type="delivery">🍔 Delivery</button>
                </div>
                <div class="queue-tabs" id="queue-tabs">
                    <button class="queue-tab active" data-queue="all">All</button>
                    <button class="queue-tab" data-queue="dispatch">Dispatch</button>
                    <button class="queue-tab" data-queue="booked">Booked</button>
                    <button class="queue-tab" data-queue="completed">Completed</button>
                    <button class="queue-tab" data-queue="cancelled">Cancelled</button>
                </div>
                <span class="trip-count" id="queue-count">{{ count($rides) }} Rides</span>
            </div>
            <div class="queue-table-wrap">
                <table class="queue-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Ride</th>
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
                            <tr data-queue="{{ $ride['queue'] }}" data-type="{{ $ride['ride_type'] === 'delivery' ? 'delivery' : 'ride' }}">
                                <td class="muted-cell">{{ $ride['time'] }}</td>
                                <td>RIDE-{{ $ride['id'] }}</td>
                                <td>
                                    <span class="trip-type-badge trip-type-{{ $ride['ride_type'] }}">
                                        {{ $ride['ride_type'] === 'delivery' ? 'Delivery' : 'Taxi' }}
                                    </span>
                                </td>
                                <td class="muted-cell">{{ $ride['pickup'] }}</td>
                                <td class="muted-cell">{{ $ride['dropoff'] }}</td>
                                <td>{{ $ride['driver'] ?? 'Not assigned' }}</td>
                                <td>{{ $ride['passenger'] ?? '--' }}</td>
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
                                    @if(in_array($ride['queue'], ['dispatch', 'booked']))
                                        <button type="button" class="queue-edit-btn" data-id="{{ $ride['id'] }}" data-status="{{ $ride['status'] }}" data-pickup="{{ $ride['pickup'] }}" data-dropoff="{{ $ride['dropoff'] }}" data-type="{{ $ride['ride_type'] }}" data-queue="{{ $ride['queue'] }}" data-fare="{{ $ride['fare'] }}">
                                            <i class="fas fa-pen"></i> Edit
                                        </button>
                                    @else
                                        --
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr id="queue-empty-row"><td colspan="11" class="queue-empty">No rides in the queue</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Ride Modal -->
    <div class="edit-ride-modal-overlay" id="editRideModalOverlay">
        <div class="edit-ride-modal">
            <h4>Edit Ride</h4>
            <form id="editRideForm">
                <input type="hidden" id="edit_ride_id">
                <div class="field">
                    <label for="edit_ride_status">Ride Status</label>
                    <select id="edit_ride_status" class="form-control">
                        <option value="requested">Requested</option>
                        <option value="accepted">Accepted</option>
                        <option value="en_route">En Route</option>
                        <option value="arrived">Arrived</option>
                        <option value="started">Started</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="field" style="position: relative;">
                    <label for="edit_pickup_location">Pickup Location</label>
                    <input type="text" id="edit_pickup_location" class="form-control" placeholder="Start typing to change...">
                    <div class="autocomplete-dropdown" id="edit-pickup-autocomplete"></div>
                    <small class="text-muted" id="edit_pickup_current"></small>
                </div>
                <div class="field" style="position: relative;">
                    <label for="edit_dropoff_location">Dropoff Location</label>
                    <input type="text" id="edit_dropoff_location" class="form-control" placeholder="Start typing to change...">
                    <div class="autocomplete-dropdown" id="edit-dropoff-autocomplete"></div>
                    <small class="text-muted" id="edit_dropoff_current"></small>
                </div>
                <div class="field" id="edit_assign_driver_field" style="display: none;">
                    <label for="edit_assign_driver" id="edit_assign_driver_label">Assign Driver</label>
                    <select id="edit_assign_driver" class="form-control">
                        <option value="">-- Leave unassigned --</option>
                    </select>
                    <small class="text-muted" id="edit_assign_driver_hint">Only available for unclaimed rides/jobs.</small>
                </div>
                <div class="field" id="edit_assign_eta_field" style="display: none;">
                    <label for="edit_assign_eta">Rider ETA (minutes)</label>
                    <input type="number" id="edit_assign_eta" class="form-control" min="0" placeholder="15">
                </div>
                @can('edit ride payment')
                    <div class="field">
                        <label for="edit_ride_fare">Ride Amount (Rs)</label>
                        <input type="number" id="edit_ride_fare" class="form-control" min="0" step="0.01"
                            placeholder="Leave blank to keep current">
                        <small class="text-muted" id="edit_ride_fare_current"></small>
                    </div>
                @endcan
                <div class="edit-ride-modal-actions">
                    <button type="button" class="btn btn-label-secondary" id="editRideCancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <!-- Google Maps JS API -->
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}"></script>

    <script>
        // Global variables
        let map, pickupMarker, destinationMarker, directionsRenderer;
        const directionsService = new google.maps.DirectionsService();
        let pickupCoordinates = null;
        let destinationCoordinates = null;
        let debounceTimer;

        // Initialize the map centered on Pakistan
        function initMap() {
            map = new google.maps.Map(document.getElementById('map'), {
                center: { lat: 30.3753, lng: 69.3451 },
                zoom: 6,
            });
        }

        // Colored-circle SVG marker icon, used for the pickup/destination
        // pins below -- replaces Leaflet's divIcon (see
        // dashboard.live-tracking.index for the driver/anomaly-marker version
        // of this same helper, which moved there with the live map).
        function svgCircleIcon({ color, size = 44, label = '', pulse = false, bg = 'white' } = {}) {
            const r = size / 2 - 3;
            const pulseAnim = pulse
                ? `<animate attributeName="r" values="${r};${r + 3};${r}" dur="1.2s" repeatCount="indefinite" />
                   <animate attributeName="opacity" values="1;0.55;1" dur="1.2s" repeatCount="indefinite" />`
                : '';
            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}">` +
                `<circle cx="${size / 2}" cy="${size / 2}" r="${r}" fill="${bg}" stroke="${color}" stroke-width="3">${pulseAnim}</circle>` +
                (label ? `<text x="${size / 2}" y="${size / 2 + 5}" text-anchor="middle" font-size="13" font-weight="bold" fill="${color}" font-family="Arial, sans-serif">${label}</text>` : '') +
                `</svg>`;

            return {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
                scaledSize: new google.maps.Size(size, size),
                anchor: new google.maps.Point(size / 2, size / 2),
            };
        }

        // Still used by the Driver #ID auto-assign / nearest-driver lookup
        // below -- unrelated to the map now that driver markers live on the
        // Live Tracking page.
        const drivers = @json($drivers);

        // =============================================
        // 1. Auto-assign driver when typing Driver ID
        // =============================================
        const driverIdInput = document.getElementById('driverIdInput');
        const hiddenDriverId = document.getElementById('driver_id');

        if (driverIdInput) {
            driverIdInput.addEventListener('input', function () {
                const enteredId = this.value.trim();

                // Only proceed if it's a non-empty numeric value
                if (!enteredId || isNaN(enteredId)) {
                    return;
                }

                // Find driver by ID
                const driver = drivers.find(d => d.id == enteredId);

                if (driver && !driver.has_vehicle) {
                    hiddenDriverId.value = '';
                    showNotification(`Driver ${driver.name} (ID: ${driver.id}) has no registered vehicle -- can't be assigned a ride until they add one.`, 'error');
                } else if (driver) {
                    // Auto-assign
                    hiddenDriverId.value = driver.id;
                    updateDriverCard(driver);
                    showNotification(`Driver ${driver.name} (ID: ${driver.id}) auto-assigned`, 'success');
                } else {
                    showNotification(`No driver found with ID: ${enteredId}`, 'error');
                }
            });

            // Explicit opt-in alternative to typing an ID -- picks the closest
            // available, vehicle-equipped driver to the pickup point already set
            // on the map. Doesn't run automatically so it never silently
            // overrides a driver ID the operator typed on purpose.
            const nearestDriverBtn = document.getElementById('assignNearestDriverBtn');
            if (nearestDriverBtn) {
                nearestDriverBtn.addEventListener('click', function () {
                    if (!pickupCoordinates) {
                        showNotification('Set a pickup location first', 'warning');
                        return;
                    }
                    const nearestDriver = getNearestDriver(pickupCoordinates[0], pickupCoordinates[1]);
                    if (!nearestDriver) {
                        showNotification('No available driver with a registered vehicle found nearby', 'error');
                        return;
                    }
                    driverIdInput.value = nearestDriver.id;
                    hiddenDriverId.value = nearestDriver.id;
                    updateDriverCard(nearestDriver);
                    showNotification(`Nearest driver assigned: ${nearestDriver.name} (ID: ${nearestDriver.id})`, 'success');
                });
            }

            // Optional: also trigger on blur / enter key
            driverIdInput.addEventListener('blur', function () {
                if (this.value.trim() && !hiddenDriverId.value) {
                    showNotification('Please enter a valid driver ID', 'warning');
                }
            });
        }

        async function fetchFare(vehicleTypeId, distanceKm) {
            try {
                const response = await fetch('/api/calculate-distance-fare', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        vehicle_type_id: vehicleTypeId,
                        distance_km: distanceKm
                    })
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    console.error('Fare API error:', errorData);
                    throw new Error('Fare calculation failed');
                }

                return await response.json();
            } catch (error) {
                console.error('Fare API error:', error);
                showNotification('Failed to calculate fare. Please try again.', 'error');
                return null;
            }
        }

        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = 'notification';

            let icon = 'info-circle';
            if (type === 'success') icon = 'check-circle';
            if (type === 'error') icon = 'exclamation-circle';
            if (type === 'warning') icon = 'exclamation-triangle';

            notification.innerHTML = `
                <i class="fas fa-${icon}" style="color: ${type === 'error' ? '#ef4444' : type === 'success' ? '#10b981' : '#2563eb'}"></i>
                <span>${message}</span>
            `;

            document.body.appendChild(notification);

            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideIn 0.3s ease-out reverse';
                setTimeout(() => {
                    if (notification.parentNode) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Search locations using Nominatim API (free, no key required)
        async function searchLocations(query, isPickup) {
            if (!query || query.length < 3) {
                return [];
            }

            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=pk`, {
                        headers: {
                            'User-Agent': 'TaxiDispatchSystem/1.0'
                        }
                    }
                );

                if (!response.ok) {
                    throw new Error('Search failed');
                }

                const data = await response.json();
                return data.map(item => ({
                    name: item.display_name.split(',')[0],
                    address: item.display_name,
                    lat: parseFloat(item.lat),
                    lng: parseFloat(item.lon),
                    type: item.type,
                    importance: item.importance
                }));
            } catch (error) {
                console.error('Search error:', error);
                showNotification('Search service temporarily unavailable', 'error');
                return [];
            }
        }

        // Display autocomplete suggestions
        async function showAutocomplete(input, dropdown, isPickup) {
            const value = input.value.trim();
            dropdown.innerHTML = '';

            if (value.length < 3) {
                dropdown.style.display = 'none';
                return;
            }

            // Show loading
            const loadingItem = document.createElement('div');
            loadingItem.className = 'loading-spinner';
            loadingItem.innerHTML = '<i class="fas fa-spinner"></i> Searching locations...';
            dropdown.appendChild(loadingItem);
            dropdown.style.display = 'block';

            // Clear previous debounce timer
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }

            // Debounce API calls
            debounceTimer = setTimeout(async () => {
                const locations = await searchLocations(value, isPickup);

                if (locations.length === 0) {
                    dropdown.innerHTML = '<div class="loading-spinner">No locations found</div>';
                    return;
                }

                dropdown.innerHTML = '';

                locations.forEach(location => {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item';

                    let icon = 'map-marker-alt';
                    if (location.type === 'airport') icon = 'plane';
                    if (location.type === 'hotel') icon = 'hotel';
                    if (location.type === 'restaurant') icon = 'utensils';
                    if (location.type === 'hospital') icon = 'hospital';
                    if (location.type === 'mall') icon = 'shopping-cart';

                    item.innerHTML = `
                        <i class="fas fa-${icon} location-icon"></i>
                        <div class="location-details">
                            <div class="location-name">${location.name}</div>
                            <div class="location-address">${location.address}</div>
                        </div>
                    `;

                    item.addEventListener('click', () => {
                        input.value = location.name;
                        dropdown.style.display = 'none';

                        if (isPickup) {
                            updatePickupLocation(location.lat, location.lng, location
                                .address);
                        } else {
                            updateDestinationLocation(location.lat, location.lng, location
                                .address);
                        }
                    });

                    dropdown.appendChild(item);
                });
            }, 500); // 500ms debounce
        }

        function getNearestDriver(pickupLat, pickupLng) {
            if (!pickupLat || !pickupLng || !drivers || drivers.length === 0) return null;

            let nearestDriver = null;
            let minDistance = Infinity;

            drivers.forEach(driver => {
                if (driver.status !== 'available') return; // only available drivers
                if (!driver.has_vehicle) return; // can't be assigned a ride without one
                if (!driver.lat || !driver.lng) return;

                // Simple Euclidean distance (approximate)
                const dx = pickupLat - driver.lat;
                const dy = pickupLng - driver.lng;
                const distance = Math.sqrt(dx * dx + dy * dy);

                if (distance < minDistance) {
                    minDistance = distance;
                    nearestDriver = driver;
                }
            });

            return nearestDriver;
        }

        function updatePickupLocation(lat, lng, address) {
            pickupCoordinates = [lat, lng];

            // Remove existing pickup marker
            if (pickupMarker) {
                pickupMarker.setMap(null);
            }

            // Add new pickup marker
            pickupMarker = new google.maps.Marker({
                position: { lat, lng },
                map: map,
                icon: svgCircleIcon({ color: '#2563eb', size: 40, label: 'P' }),
            });
            const pickupInfoWindow = new google.maps.InfoWindow({ content: `<b>Pickup Location</b><br>${address}` });
            pickupMarker.addListener('click', () => pickupInfoWindow.open(map, pickupMarker));

            // Update route if destination exists
            if (destinationCoordinates) {
                updateRoute();
            }

            showNotification('Pickup location set', 'success');
        }

        // Update destination location on map
        function updateDestinationLocation(lat, lng, address) {
            destinationCoordinates = [lat, lng];

            // Remove existing destination marker
            if (destinationMarker) {
                destinationMarker.setMap(null);
            }

            // Add new destination marker
            destinationMarker = new google.maps.Marker({
                position: { lat, lng },
                map: map,
                icon: svgCircleIcon({ color: '#ef4444', size: 40, label: 'D' }),
            });
            const destinationInfoWindow = new google.maps.InfoWindow({ content: `<b>Destination</b><br>${address}` });
            destinationMarker.addListener('click', () => destinationInfoWindow.open(map, destinationMarker));

            // Update route if pickup exists
            if (pickupCoordinates) {
                updateRoute();
            }

            showNotification('Destination set', 'success');
        }

        // Update route using Google Directions API
        function updateRoute() {
            if (!pickupCoordinates || !destinationCoordinates) {
                return;
            }

            if (!directionsRenderer) {
                directionsRenderer = new google.maps.DirectionsRenderer({
                    map: map,
                    suppressMarkers: true, // keep our own pickup/destination markers
                    polylineOptions: {
                        strokeColor: '#2563eb',
                        strokeWeight: 4,
                        strokeOpacity: 0.7,
                    },
                });
            }

            directionsService.route({
                origin: { lat: pickupCoordinates[0], lng: pickupCoordinates[1] },
                destination: { lat: destinationCoordinates[0], lng: destinationCoordinates[1] },
                travelMode: google.maps.TravelMode.DRIVING,
            }, async function(result, status) {
                if (status !== google.maps.DirectionsStatus.OK || !result.routes || !result.routes.length) {
                    showNotification('Could not calculate route. Using straight line distance.', 'warning');
                    calculateFallbackDistance();
                    return;
                }

                directionsRenderer.setDirections(result);

                const leg = result.routes[0].legs[0];
                const distance = (leg.distance.value / 1000).toFixed(1); // meters -> km
                const time = Math.round(leg.duration.value / 60); // seconds -> minutes

                document.getElementById('ride_distance').value = distance;

                const vehicleTypeId = document.querySelector('#vehicle_type_id')?.value || null;

                // Call fare API
                const fareData = await fetchFare(vehicleTypeId, distance);

                if (fareData) {
                    document.getElementById('distance').textContent = `${distance} km`;
                    document.getElementById('time').textContent = `${time} min`;

                    const originalFareEl = document.getElementById('original-fare');
                    const finalFareEl = document.getElementById('final-fare');
                    const boostEl = document.getElementById('boost-multiplier');

                    // Only show boost if boost is active
                    if (fareData.is_boost && fareData.boost_multiplier > 1) {
                        // Show original fare crossed out
                        originalFareEl.style.display = 'inline';
                        originalFareEl.textContent = `Rs ${fareData.total_fare}`;

                        // Show boosted fare
                        finalFareEl.textContent = `Rs ${fareData.boosted_fare}`;
                        finalFareEl.style.color = '#ef4444';

                        // Show multiplier in sup
                        boostEl.style.display = 'inline';
                        boostEl.textContent = `x${fareData.boost_multiplier}`;
                    } else {
                        // No boost
                        originalFareEl.style.display = 'none';
                        finalFareEl.textContent = `Rs ${fareData.total_fare}`;
                        finalFareEl.style.color = ''; // default
                        boostEl.style.display = 'none';
                    }
                }

                // Fit map bounds
                const bounds = new google.maps.LatLngBounds();
                bounds.extend({ lat: pickupCoordinates[0], lng: pickupCoordinates[1] });
                bounds.extend({ lat: destinationCoordinates[0], lng: destinationCoordinates[1] });
                map.fitBounds(bounds, 60);
            });
        }

        function updateDriverCard(driver) {
            if (!driver) return;

            const initials = driver.name ? driver.name.substring(0, 2).toUpperCase() : 'NA';
            const driverCard = document.querySelector('.driver-card');

            driverCard.querySelector('.driver-avatar').innerHTML =
                `${initials}<div class="driver-status status-online"></div>`;
            driverCard.querySelector('.driver-info h3').textContent = driver.name;
            driverCard.querySelector('.driver-info .driver-meta span:nth-child(1)').innerHTML =
                `<i class="fas fa-id-badge"></i> ID: ${driver.id}`;
            driverCard.querySelector('.driver-info .driver-meta span:nth-child(2)').innerHTML =
                `<i class="fas fa-car"></i> ${driver.vehicle}`;
            driverCard.querySelector('#vehicle_type_id').value = driver.vehicle_type_id;
            driverCard.querySelector('#driver_phone').value = driver.phone;
        }

        // Calculate fallback distance if routing fails
        function calculateFallbackDistance() {
            if (!pickupCoordinates || !destinationCoordinates) return;

            const distance = calculateDistance(
                pickupCoordinates[0], pickupCoordinates[1],
                destinationCoordinates[0], destinationCoordinates[1]
            );

            const time = Math.round(distance / 40 * 60);
            const fare = Math.round(100 + distance * 25);

            document.getElementById('distance').textContent = `${distance.toFixed(1)} km`;
            document.getElementById('time').textContent = `${time} min`;
            document.getElementById('fare').textContent = `Rs ${fare}`;
        }

        // Calculate distance between coordinates (Haversine formula)
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Earth's radius in km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();

            // Setup autocomplete for pickup and destination
            const pickupInput = document.getElementById('pickup-location');
            const pickupDropdown = document.getElementById('pickup-autocomplete');
            const destInput = document.getElementById('destination');
            const destDropdown = document.getElementById('destination-autocomplete');

            pickupInput.addEventListener('input', () => {
                showAutocomplete(pickupInput, pickupDropdown, true);
            });

            destInput.addEventListener('input', () => {
                showAutocomplete(destInput, destDropdown, false);
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!pickupInput.contains(e.target) && !pickupDropdown.contains(e.target)) {
                    pickupDropdown.style.display = 'none';
                }
                if (!destInput.contains(e.target) && !destDropdown.contains(e.target)) {
                    destDropdown.style.display = 'none';
                }
            });

            document.getElementById('assign-trip').addEventListener('click', async function () {

                const button = this;

                /* ======================
                GET ELEMENTS / VALUES
                ====================== */
                const driverNameEl = document.querySelector('.driver-info h3');
                const driverIdEl = document.getElementById('driver_id');
                const pickupEl = document.getElementById('pickup-location');
                const destinationEl = document.getElementById('destination');
                const vehicleTypeEl = document.getElementById('vehicle_type_id');
                const passengerNameEl = document.getElementById('passenger_name');
                const passengerPhoneEl = document.getElementById('passenger_phone');
                const fareEl = document.getElementById('final-fare');
                const timeEl = document.getElementById('time');
                const driver_id_input = document.getElementById('driverIdInput');

                /* ======================
                VALIDATIONS (ELEMENT)
                ====================== */
                if (!driverNameEl) return showNotification('Driver info not found!', 'error');
                if (!driverIdEl) return showNotification('Driver ID field missing!', 'error');
                if (!pickupEl) return showNotification('Pickup field missing!', 'error');
                if (!destinationEl) return showNotification('Destination field missing!', 'error');
                if (!vehicleTypeEl) return showNotification('Vehicle type not selected!', 'error');
                if (!passengerNameEl) return showNotification('Passenger name field missing!', 'error');
                if (!passengerPhoneEl) return showNotification('Passenger phone field missing!', 'error');
                if (!fareEl) return showNotification('Fare not calculated yet!', 'error');
                if (!timeEl) return showNotification('Trip time not available!', 'error');

                /* ======================
                VALUES
                ====================== */
                const driverName = driverNameEl.textContent.trim();
                const driverId = driverIdEl.value;
                const pickup = pickupEl.value.trim();
                const destination = destinationEl.value.trim();
                const vehicleTypeId = vehicleTypeEl.value;
                const passengerName = passengerNameEl.value.trim();
                const passengerPhone = passengerPhoneEl.value.trim();

                /* ======================
                VALIDATIONS (VALUES)
                ====================== */
                if (!driverId) return showNotification('Please select a driver!', 'error');
                if (!vehicleTypeId) return showNotification('Please select a vehicle type!', 'error');
                if (!pickup) return showNotification('Please select pickup location!', 'error');
                if (!destination) return showNotification('Please select destination location!', 'error');

                if (!pickupCoordinates || !destinationCoordinates) {
                    return showNotification('Please select valid locations from suggestions!', 'error');
                }

                if (!passengerName) return showNotification('Passenger name is required!', 'error');
                if (!passengerPhone) return showNotification('Passenger phone is required!', 'error');

                // Basic phone validation
                if (!/^[0-9+\-\s]{7,15}$/.test(passengerPhone)) {
                    return showNotification('Invalid passenger phone number!', 'error');
                }

                /* ======================
                LOADING STATE
                ====================== */
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
                button.disabled = true;

                /* ======================
                API CALL
                ====================== */
                try {
                    const response = await fetch(@json(route('dashboard.custom-rides.store')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute('content')
                        },
                        body: JSON.stringify({
                            vehicle_type_id: vehicleTypeId,
                            driver_id: driverId,
                            promo_code_id: null,
                            pickup_latitude: pickupCoordinates[0].toString(),
                            pickup_longitude: pickupCoordinates[1].toString(),
                            dropoff_latitude: destinationCoordinates[0].toString(),
                            dropoff_longitude: destinationCoordinates[1].toString(),
                            distance_km: document.getElementById('ride_distance')?.value || null,
                            duration_minutes: timeEl.textContent.replace(' min', ''),
                            subtotal: fareEl.textContent.replace('Rs ', '').trim(),
                            discount_amount: "0",
                            total_fare: fareEl.textContent.replace('Rs ', '').trim(),
                            passenger_name: passengerName,
                            passenger_phone: passengerPhone,
                            driver_id_input: driver_id_input.value || null,
                        })
                    });

                    const result = await response.json();

                    if (response.ok) {
                        button.innerHTML = '<i class="fas fa-check"></i> Assigned!';
                        button.style.backgroundColor = '#10b981';

                        showNotification(
                            `Ride assigned successfully! Ride ID: ${result.ride_id}`,
                            'success'
                        );

                        resetBookingForm();
                        refreshDispatchStats();
                    } else {
                        showNotification(result.message || 'Ride assignment failed!', 'error');
                    }

                } catch (error) {
                    console.error('Assign Trip Error:', error);
                    showNotification('Network error. Please try again.', 'error');
                } finally {
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.style.backgroundColor = '';
                        button.disabled = false;
                    }, 2000);
                }
            });

            // Message driver button
            document.getElementById('message-driver').addEventListener('click', function() {
                const driverName = document.querySelector('.driver-info h3').textContent;
                const driverPhone = document.querySelector('#driver_phone').value;

                if (!driverPhone) {
                    showNotification('Driver phone number not available!', 'error');
                    return;
                }

                showNotification(`Opening WhatsApp chat with ${driverName}...`, 'info');

                // Open WhatsApp in a new tab
                window.open(`https://wa.me/92${driverPhone}`, '_blank');
            });

        });

        // Assign driver function
        window.assignDriver = async function(driverId) {
            const driver = drivers.find(d => d.id === driverId);
            if (driver) {
                document.getElementById('driverIdInput').value = driver.id;
                document.getElementById('driver_id').value = driver.id;
                // Update driver card (name, ID, vehicle, phone, vehicle_type_id)
                updateDriverCard(driver);

                showNotification(`Assigned ${driver.name} from ${driver.city} to the current trip!`, 'success');

                const distance = document.getElementById('ride_distance').value || 0;
                const vehicleTypeId = driver.vehicle_type_id || null;

                // Call fare API
                const fareData = await fetchFare(vehicleTypeId, distance);

                if (fareData) {
                    document.getElementById('distance').textContent = `${distance} km`;
                    // document.getElementById('time').textContent = `${time} min`;

                    const originalFareEl = document.getElementById('original-fare');
                    const finalFareEl = document.getElementById('final-fare');
                    const boostEl = document.getElementById('boost-multiplier');

                    // Only show boost if boost is active
                    if (fareData.is_boost && fareData.boost_multiplier > 1) {
                        // Show original fare crossed out
                        originalFareEl.style.display = 'inline';
                        originalFareEl.textContent = `Rs ${fareData.total_fare}`;

                        // Show boosted fare
                        finalFareEl.textContent = `Rs ${fareData.boosted_fare}`;
                        finalFareEl.style.color = '#ef4444';

                        // Show multiplier in sup
                        boostEl.style.display = 'inline';
                        boostEl.textContent = `x${fareData.boost_multiplier}`;
                    } else {
                        // No boost
                        originalFareEl.style.display = 'none';
                        finalFareEl.textContent = `Rs ${fareData.total_fare}`;
                        finalFareEl.style.color = ''; // default
                        boostEl.style.display = 'none';
                    }
                }
            }
        };

        // =============================================
        // Reset booking form after a successful assign
        // =============================================
        function resetBookingForm() {
            ['pickup-location', 'destination', 'passenger_name', 'passenger_phone']
                .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

            pickupCoordinates = null;
            destinationCoordinates = null;

            [pickupMarker, destinationMarker].forEach(marker => { if (marker) marker.setMap(null); });
            pickupMarker = null;
            destinationMarker = null;

            if (directionsRenderer) {
                directionsRenderer.setMap(null);
                directionsRenderer = null;
            }

            document.getElementById('distance').textContent = '0 km';
            document.getElementById('time').textContent = '0 min';
            document.getElementById('final-fare').textContent = 'Rs 0';
            document.getElementById('original-fare').style.display = 'none';
            document.getElementById('boost-multiplier').style.display = 'none';
        }

        // =============================================
        // Live dispatch stats + ride queue polling
        // =============================================
        const dispatchStatsUrl = @json(route('dashboard.custom-rides.stats'));
        const queueStatusBadge = {
            completed: 'status-completed',
            cancelled: 'status-cancelled',
            requested: 'status-pending',
        };
        let activeQueueFilter = 'all';
        let activeTypeFilter = 'all';

        function renderQueueTable(rides) {
            const tbody = document.getElementById('queue-table-body');
            if (!rides.length) {
                tbody.innerHTML = '<tr id="queue-empty-row"><td colspan="11" class="queue-empty">No rides in the queue</td></tr>';
                return;
            }

            tbody.innerHTML = rides.map(ride => {
                const badgeClass = queueStatusBadge[ride.status] || 'status-active';
                const statusLabel = ride.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                const canEdit = ride.queue === 'dispatch' || ride.queue === 'booked';
                const rideType = ride.ride_type === 'delivery' ? 'delivery' : 'ride';
                const typeLabel = rideType === 'delivery' ? 'Delivery' : 'Taxi';
                const actionsCell = canEdit
                    ? `<button type="button" class="queue-edit-btn" data-id="${ride.id}" data-status="${ride.status}" data-pickup="${ride.pickup ?? ''}" data-dropoff="${ride.dropoff ?? ''}" data-type="${rideType}" data-queue="${ride.queue}" data-fare="${ride.fare ?? ''}"><i class="fas fa-pen"></i> Edit</button>`
                    : '--';
                return `
                    <tr data-queue="${ride.queue}" data-type="${rideType}">
                        <td class="muted-cell">${ride.time ?? ''}</td>
                        <td>RIDE-${ride.id}</td>
                        <td><span class="trip-type-badge trip-type-${rideType}">${typeLabel}</span></td>
                        <td class="muted-cell">${ride.pickup ?? ''}</td>
                        <td class="muted-cell">${ride.dropoff ?? ''}</td>
                        <td>${ride.driver ?? 'Not assigned'}</td>
                        <td>${ride.passenger ?? '--'}</td>
                        <td class="muted-cell">${ride.phone ?? '--'}</td>
                        <td>Rs ${Math.round(ride.fare ?? 0)}</td>
                        <td><span class="trip-status ${badgeClass}">${statusLabel}</span></td>
                        <td>${actionsCell}</td>
                    </tr>
                `;
            }).join('');

            applyQueueFilter();
        }

        function applyQueueFilter() {
            const rows = document.querySelectorAll('#queue-table-body tr[data-queue]');
            let visibleCount = 0;
            rows.forEach(row => {
                const matchesQueue = activeQueueFilter === 'all' || row.dataset.queue === activeQueueFilter;
                const matchesType = activeTypeFilter === 'all' || row.dataset.type === activeTypeFilter;
                const show = matchesQueue && matchesType;
                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });
            document.getElementById('queue-count').textContent = `${visibleCount} Rides`;
        }

        async function refreshDispatchStats() {
            try {
                const response = await fetch(dispatchStatsUrl, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) return;

                const data = await response.json();

                document.getElementById('stat-available').textContent = data.driverAvailableCount;
                document.getElementById('stat-busy').textContent = data.driverBusyCount;
                document.getElementById('stat-dispatch').textContent = data.rideCounts.dispatch;
                document.getElementById('stat-booked').textContent = data.rideCounts.booked;
                document.getElementById('stat-completed').textContent = data.rideCounts.completed;
                document.getElementById('stat-cancelled').textContent = data.rideCounts.cancelled;

                renderQueueTable(data.rides);
            } catch (error) {
                console.error('Dispatch stats refresh failed:', error);
            }
        }

        document.getElementById('queue-type-tabs').addEventListener('click', function(e) {
            const tab = e.target.closest('.queue-tab');
            if (!tab) return;

            this.querySelectorAll('.queue-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeTypeFilter = tab.dataset.type;
            applyQueueFilter();
        });

        document.getElementById('queue-tabs').addEventListener('click', function(e) {
            const tab = e.target.closest('.queue-tab');
            if (!tab) return;

            this.querySelectorAll('.queue-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeQueueFilter = tab.dataset.queue;
            applyQueueFilter();
        });

        // =============================================
        // Edit Ride (Dispatch / Booked rows only)
        // =============================================
        const editRideModalOverlay = document.getElementById('editRideModalOverlay');
        const editRideForm = document.getElementById('editRideForm');
        const editRideIdEl = document.getElementById('edit_ride_id');
        const editRideStatusEl = document.getElementById('edit_ride_status');
        const editPickupInput = document.getElementById('edit_pickup_location');
        const editDropoffInput = document.getElementById('edit_dropoff_location');
        const editPickupDropdown = document.getElementById('edit-pickup-autocomplete');
        const editDropoffDropdown = document.getElementById('edit-dropoff-autocomplete');
        const editPickupCurrentEl = document.getElementById('edit_pickup_current');
        const editDropoffCurrentEl = document.getElementById('edit_dropoff_current');
        const editAssignDriverField = document.getElementById('edit_assign_driver_field');
        const editAssignDriverSelect = document.getElementById('edit_assign_driver');
        const editAssignDriverHint = document.getElementById('edit_assign_driver_hint');
        const editAssignEtaField = document.getElementById('edit_assign_eta_field');
        const editAssignEtaInput = document.getElementById('edit_assign_eta');

        // New coordinates only get set here if the admin actually picks a new
        // location from the autocomplete — otherwise these stay null and the
        // ride's existing pickup/dropoff are left untouched on save.
        let editPickupCoords = null;
        let editDropoffCoords = null;
        let editAutocompleteDebounce = null;

        function openEditRideModal(rideId, status, pickup, dropoff, type, queue, fare) {
            editRideIdEl.value = rideId;
            editRideStatusEl.value = status;

            editPickupCoords = null;
            editDropoffCoords = null;
            editPickupInput.value = '';
            editDropoffInput.value = '';
            editPickupCurrentEl.textContent = pickup ? `Current: ${pickup}` : '';
            editDropoffCurrentEl.textContent = dropoff ? `Current: ${dropoff}` : '';

            // Fare field only renders for users with 'edit ride payment'.
            const editRideFareEl = document.getElementById('edit_ride_fare');
            if (editRideFareEl) {
                editRideFareEl.value = '';
                document.getElementById('edit_ride_fare_current').textContent =
                    (fare !== undefined && fare !== '') ? `Current: Rs ${fare}` : '';
            }

            // A cancelled ride can also be reassigned to a different driver
            // (Live Ops Task 6) -- status is force-set to 'requested' below
            // when the admin actually picks a driver for it, relaunching it.
            const isCancelled = queue === 'cancelled';
            const canAssignDriver = (queue === 'dispatch' || isCancelled) && (type === 'ride' || type === 'delivery');
            const isDelivery = type === 'delivery';
            editAssignDriverSelect.value = '';
            editAssignEtaInput.value = '';
            if (canAssignDriver) {
                editAssignDriverField.style.display = '';
                editAssignDriverHint.textContent = isCancelled
                    ? 'Reassigning will relaunch this cancelled ride and notify the previous driver it was taken back to base.'
                    : (isDelivery
                        ? 'Only delivery-capable drivers are listed for a delivery job.'
                        : 'Only available for unclaimed rides/jobs.');
                editAssignEtaField.style.display = isDelivery ? '' : 'none';
                editAssignDriverSelect.innerHTML = '<option value="">-- Leave unassigned --</option>' +
                    drivers
                        .filter(d => d.status === 'available' && (!isDelivery || d.is_delivery))
                        .map(d => `<option value="${d.id}">${d.name} (#${d.id}) - ${d.vehicle}</option>`)
                        .join('');
            } else {
                editAssignDriverField.style.display = 'none';
                editAssignEtaField.style.display = 'none';
            }

            editRideModalOverlay.classList.add('open');
        }

        function closeEditRideModal() {
            editRideModalOverlay.classList.remove('open');
            editPickupDropdown.style.display = 'none';
            editDropoffDropdown.style.display = 'none';
        }

        document.getElementById('queue-table-body').addEventListener('click', function (e) {
            const btn = e.target.closest('.queue-edit-btn');
            if (!btn) return;
            openEditRideModal(btn.dataset.id, btn.dataset.status, btn.dataset.pickup, btn.dataset.dropoff, btn.dataset.type, btn.dataset.queue, btn.dataset.fare);
        });

        document.getElementById('editRideCancelBtn').addEventListener('click', closeEditRideModal);
        editRideModalOverlay.addEventListener('click', function (e) {
            if (e.target === editRideModalOverlay) closeEditRideModal();
        });

        // Self-contained autocomplete for the edit modal — deliberately separate
        // from the "New Ride" panel's pickup/destination globals above, so editing
        // an existing ride's location can never disturb an in-progress new-ride form.
        function wireEditAutocomplete(input, dropdown, onSelect) {
            input.addEventListener('input', function () {
                const value = input.value.trim();
                dropdown.innerHTML = '';

                if (value.length < 3) {
                    dropdown.style.display = 'none';
                    return;
                }

                const loadingItem = document.createElement('div');
                loadingItem.className = 'loading-spinner';
                loadingItem.innerHTML = '<i class="fas fa-spinner"></i> Searching locations...';
                dropdown.appendChild(loadingItem);
                dropdown.style.display = 'block';

                clearTimeout(editAutocompleteDebounce);
                editAutocompleteDebounce = setTimeout(async () => {
                    const locations = await searchLocations(value, true);

                    if (locations.length === 0) {
                        dropdown.innerHTML = '<div class="loading-spinner">No locations found</div>';
                        return;
                    }

                    dropdown.innerHTML = '';
                    locations.forEach(location => {
                        const item = document.createElement('div');
                        item.className = 'autocomplete-item';
                        item.innerHTML = `
                            <i class="fas fa-map-marker-alt location-icon"></i>
                            <div class="location-details">
                                <div class="location-name">${location.name}</div>
                                <div class="location-address">${location.address}</div>
                            </div>
                        `;
                        item.addEventListener('click', () => {
                            input.value = location.name;
                            dropdown.style.display = 'none';
                            onSelect(location.lat, location.lng);
                        });
                        dropdown.appendChild(item);
                    });
                }, 500);
            });
        }

        wireEditAutocomplete(editPickupInput, editPickupDropdown, (lat, lng) => {
            editPickupCoords = [lat, lng];
        });
        wireEditAutocomplete(editDropoffInput, editDropoffDropdown, (lat, lng) => {
            editDropoffCoords = [lat, lng];
        });

        editRideForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const rideId = editRideIdEl.value;
            if (!rideId) {
                return showNotification('No ride selected to update!', 'error');
            }
            const submitBtn = editRideForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Saving...';

            try {
                const isReassigningCancelled = editRideStatusEl.value === 'cancelled'
                    && editAssignDriverField.style.display !== 'none'
                    && editAssignDriverSelect.value;

                const payload = {
                    ride_id: rideId,
                    // Reassigning a cancelled ride relaunches it -- 'requested'
                    // is the only status a fresh admin-assignment can start
                    // from (see the parallel Custom Ride / Assign Trip flow).
                    status: isReassigningCancelled ? 'requested' : editRideStatusEl.value,
                };

                // Only include pickup/dropoff if the admin actually picked a new one.
                if (editPickupCoords) {
                    payload.pickup_latitude = editPickupCoords[0].toString();
                    payload.pickup_longitude = editPickupCoords[1].toString();
                }
                if (editDropoffCoords) {
                    payload.dropoff_latitude = editDropoffCoords[0].toString();
                    payload.dropoff_longitude = editDropoffCoords[1].toString();
                }
                if (editAssignDriverField.style.display !== 'none' && editAssignDriverSelect.value) {
                    payload.driver_id = editAssignDriverSelect.value;
                    if (editAssignEtaField.style.display !== 'none' && editAssignEtaInput.value) {
                        payload.eta_minutes = editAssignEtaInput.value;
                    }
                }

                // Only send a fare if the admin actually typed a new one.
                const editRideFareEl = document.getElementById('edit_ride_fare');
                if (editRideFareEl && editRideFareEl.value !== '') {
                    payload.total_fare = editRideFareEl.value;
                }

                const response = await fetch(`/dashboard/rides/${rideId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                });

                if (response.ok) {
                    showNotification('Ride updated successfully!', 'success');
                    closeEditRideModal();
                    refreshDispatchStats();
                } else {
                    const result = await response.json().catch(() => null);
                    showNotification(result?.message || 'Failed to update ride!', 'error');
                }
            } catch (error) {
                console.error('Edit Ride Error:', error);
                showNotification('Network error. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });

        // =============================================
        // Keyboard shortcuts: F1 assign, F2 pickup, F4 driver id, F10 destination, Esc clear
        // =============================================
        document.addEventListener('keydown', function(e) {
            const shortcutKeys = ['F1', 'F2', 'F4', 'F10', 'Escape'];
            if (!shortcutKeys.includes(e.key)) return;

            if (e.key === 'F1') {
                e.preventDefault();
                document.getElementById('assign-trip').click();
            } else if (e.key === 'F2') {
                e.preventDefault();
                document.getElementById('pickup-location').focus();
            } else if (e.key === 'F4') {
                e.preventDefault();
                document.getElementById('driverIdInput').focus();
            } else if (e.key === 'F10') {
                e.preventDefault();
                document.getElementById('destination').focus();
            } else if (e.key === 'Escape') {
                resetBookingForm();
            }
        });

        setInterval(refreshDispatchStats, 5000);
    </script>
@endsection
