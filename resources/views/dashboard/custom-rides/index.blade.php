@extends('layouts.master')

@section('title', __('Custom Rides'))

@section('css')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
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
        }

        #map {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
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

        /* Routing machine custom styles */
        .leaflet-routing-container {
            display: none;
            background: var(--surface);
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
        }

        .leaflet-routing-alt {
            max-height: 300px;
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

        /* Custom Leaflet Styles */
        .leaflet-control-zoom {
            border: none !important;
            box-shadow: var(--card-shadow) !important;
            border-radius: 8px !important;
            overflow: hidden;
        }

        .leaflet-control-zoom a {
            border-radius: 0 !important;
            border: none !important;
            width: 34px !important;
            height: 34px !important;
            line-height: 34px !important;
        }

        .leaflet-popup-content {
            font-family: 'Inter', sans-serif !important;
        }

        .custom-div-icon {
            background: transparent !important;
            border: none !important;
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
                        <input type="text" id="driverIdInput" name="driver_id_input" class="form-control"
                            placeholder="Driver ID" value="{{ $driver->id }}">
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

            <!-- Map Panel -->
            <section class="panel map-panel">
                <div id="map"></div>
            </section>
        </div>

        <!-- Ride Queue (full width) -->
        <div class="panel trips-container">
            <div class="trips-header">
                <h3><i class="fas fa-list-check"></i> Ride Queue</h3>
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
                            <tr data-queue="{{ $ride['queue'] }}">
                                <td class="muted-cell">{{ $ride['time'] }}</td>
                                <td>RIDE-{{ $ride['id'] }}</td>
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
                                        <button type="button" class="queue-edit-btn" data-id="{{ $ride['id'] }}" data-status="{{ $ride['status'] }}">
                                            <i class="fas fa-pen"></i> Edit
                                        </button>
                                    @else
                                        --
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr id="queue-empty-row"><td colspan="10" class="queue-empty">No rides in the queue</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Ride Modal -->
    <div class="edit-ride-modal-overlay" id="editRideModalOverlay">
        <div class="edit-ride-modal">
            <h4>Edit Ride Status</h4>
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
                <div class="edit-ride-modal-actions">
                    <button type="button" class="btn btn-label-secondary" id="editRideCancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet Routing Machine -->
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

    <script>
        // Global variables
        let map, pickupMarker, destinationMarker, routingControl;
        let pickupCoordinates = null;
        let destinationCoordinates = null;
        let debounceTimer;

        // Initialize the map centered on Pakistan
        function initMap() {
            map = L.map('map').setView([30.3753, 69.3451], 6);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18,
            }).addTo(map);

            // Add driver markers
            addDriverMarkers();
        }

        // Custom taxi icons
        const taxiIcon = L.divIcon({
            className: 'custom-div-icon',
            html: '<div style="background-color: white; border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border: 3px solid #10b981; box-shadow: 0 2px 5px rgba(0,0,0,0.2);"><i class="fas fa-taxi" style="color: #10b981; font-size: 20px;"></i></div>',
            iconSize: [44, 44],
            iconAnchor: [22, 22]
        });

        const taxiIconBusy = L.divIcon({
            className: 'custom-div-icon',
            html: '<div style="background-color: white; border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border: 3px solid #f59e0b; box-shadow: 0 2px 5px rgba(0,0,0,0.2);"><i class="fas fa-taxi" style="color: #f59e0b; font-size: 20px;"></i></div>',
            iconSize: [44, 44],
            iconAnchor: [22, 22]
        });

        function getDriverIcon(driver) {
            const borderColor = driver.status === 'available' ? '#10b981' : '#f59e0b';

            return L.divIcon({
                className: 'custom-div-icon',
                html: `
                    <div style="
                        background-color: white;
                        border-radius: 50%;
                        width: 44px;
                        height: 44px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border: 3px solid ${borderColor};
                        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                        font-weight: bold;
                        font-size: 14px;
                        color: #111827;
                    ">
                        ${'D-' + driver.id}
                    </div>
                `,
                iconSize: [44, 44],
                iconAnchor: [22, 22]
            });
        }


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

                if (driver) {
                    // Auto-assign
                    hiddenDriverId.value = driver.id;
                    updateDriverCard(driver);
                    showNotification(`Driver ${driver.name} (ID: ${driver.id}) auto-assigned`, 'success');
                } else {
                    // Optional: clear previous selection if invalid ID
                    // hiddenDriverId.value = '';
                    // You can also clear the card here if you want

                    showNotification(`No driver found with ID: ${enteredId}`, 'error');
                }
            });

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

        // Add driver markers to map
        function addDriverMarkers() {
            drivers.forEach(driver => {

                const lat = parseFloat(driver.lat);
                const lng = parseFloat(driver.lng);

                // Skip invalid coordinates – very important!
                if (isNaN(lat) || isNaN(lng)) {
                    console.warn(`Driver ${driver.id} (${driver.name}) has invalid coordinates: lat=${driver.lat}, lng=${driver.lng}`);
                    return;
                }

                const marker = L.marker(
                        [parseFloat(driver.lat), parseFloat(driver.lng)], {
                            icon: getDriverIcon(driver)
                        }
                    )
                    .bindPopup(`
                    <div style="padding: 10px; min-width: 200px;">
                        <h3 style="margin: 0 0 10px 0; color: #1f2937;">${driver.name}</h3>
                        <p style="margin: 5px 0; font-size: 14px;"><strong>#ID:</strong> ${driver.id}</p>
                        <p style="margin: 5px 0; font-size: 14px;"><strong>City:</strong> ${driver.city}</p>
                        <p style="margin: 5px 0; font-size: 14px;">
                            <strong>Status:</strong>
                            <span style="color: ${driver.status === 'available' ? '#10b981' : '#f59e0b'}">
                                ${driver.status === 'available' ? 'Available' : 'Busy'}
                            </span>
                        </p>
                        <p style="margin: 5px 0; font-size: 14px;"><strong>Vehicle:</strong> ${driver.vehicle ?? 'N/A'}</p>
                        <button onclick="assignDriver(${driver.id})"
                                style="margin-top: 10px; padding: 8px 16px; background-color: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-weight: 600;">
                            <i class="fas fa-paper-plane"></i> Assign Trip
                        </button>
                    </div>
                `)
                    .addTo(map);
            });
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
                map.removeLayer(pickupMarker);
            }

            // Add new pickup marker
            pickupMarker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'custom-div-icon',
                    html: '<div style="background-color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border: 3px solid #2563eb; box-shadow: 0 2px 5px rgba(0,0,0,0.2);"><i class="fas fa-location-dot" style="color: #2563eb; font-size: 18px;"></i></div>',
                    iconSize: [40, 40],
                    iconAnchor: [20, 20]
                })
            }).bindPopup(`<b>Pickup Location</b><br>${address}`).addTo(map);

            // Update route if destination exists
            if (destinationCoordinates) {
                updateRoute();
            }

            showNotification('Pickup location set', 'success');

            // --- AUTO-SELECT NEAREST DRIVER ---
            const nearestDriver = getNearestDriver(lat, lng);
            if (nearestDriver) {
                document.getElementById('driver_id').value = nearestDriver.id;
                updateDriverCard(nearestDriver);
                showNotification(`Nearest driver selected: ${nearestDriver.name}`, 'info');
            }
        }

        // Update destination location on map
        function updateDestinationLocation(lat, lng, address) {
            destinationCoordinates = [lat, lng];

            // Remove existing destination marker
            if (destinationMarker) {
                map.removeLayer(destinationMarker);
            }

            // Add new destination marker
            destinationMarker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'custom-div-icon',
                    html: '<div style="background-color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border: 3px solid #ef4444; box-shadow: 0 2px 5px rgba(0,0,0,0.2);"><i class="fas fa-flag-checkered" style="color: #ef4444; font-size: 18px;"></i></div>',
                    iconSize: [40, 40],
                    iconAnchor: [20, 20]
                })
            }).bindPopup(`<b>Destination</b><br>${address}`).addTo(map);

            // Update route if pickup exists
            if (pickupCoordinates) {
                updateRoute();
            }

            showNotification('Destination set', 'success');
        }

        // Update route using OSRM API (free)
        function updateRoute() {
            if (!pickupCoordinates || !destinationCoordinates) {
                return;
            }

            // Remove existing route
            if (routingControl) {
                map.removeControl(routingControl);
            }

            // Add new route using OSRM
            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(pickupCoordinates[0], pickupCoordinates[1]),
                    L.latLng(destinationCoordinates[0], destinationCoordinates[1])
                ],
                routeWhileDragging: false,
                showAlternatives: false,
                lineOptions: {
                    styles: [{
                        color: '#2563eb',
                        weight: 4,
                        opacity: 0.7
                    }]
                },
                createMarker: function() {
                    return null;
                }, // Don't create default markers
                router: L.Routing.osrmv1({
                    serviceUrl: 'https://router.project-osrm.org/route/v1'
                })
            }).addTo(map);

            routingControl.on('routesfound', async function(e) {
                const routes = e.routes;
                if (!routes || routes.length === 0) return;

                const route = routes[0];
                const distance = (route.summary.totalDistance / 1000).toFixed(1);
                const time = Math.round(route.summary.totalTime / 60);

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
                const bounds = L.latLngBounds([
                    [pickupCoordinates[0], pickupCoordinates[1]],
                    [destinationCoordinates[0], destinationCoordinates[1]]
                ]);
                map.fitBounds(bounds.pad(0.1));
            });




            routingControl.on('routingerror', function(e) {
                showNotification('Could not calculate route. Using straight line distance.', 'warning');
                calculateFallbackDistance();
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
                    const response = await fetch('/api/request-ride', {
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

            [pickupMarker, destinationMarker].forEach(marker => { if (marker) map.removeLayer(marker); });
            pickupMarker = null;
            destinationMarker = null;

            if (routingControl) {
                map.removeControl(routingControl);
                routingControl = null;
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

        function renderQueueTable(rides) {
            const tbody = document.getElementById('queue-table-body');
            if (!rides.length) {
                tbody.innerHTML = '<tr id="queue-empty-row"><td colspan="10" class="queue-empty">No rides in the queue</td></tr>';
                return;
            }

            tbody.innerHTML = rides.map(ride => {
                const badgeClass = queueStatusBadge[ride.status] || 'status-active';
                const statusLabel = ride.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                const canEdit = ride.queue === 'dispatch' || ride.queue === 'booked';
                const actionsCell = canEdit
                    ? `<button type="button" class="queue-edit-btn" data-id="${ride.id}" data-status="${ride.status}"><i class="fas fa-pen"></i> Edit</button>`
                    : '--';
                return `
                    <tr data-queue="${ride.queue}">
                        <td class="muted-cell">${ride.time ?? ''}</td>
                        <td>RIDE-${ride.id}</td>
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
                const show = activeQueueFilter === 'all' || row.dataset.queue === activeQueueFilter;
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

        function openEditRideModal(rideId, status) {
            editRideIdEl.value = rideId;
            editRideStatusEl.value = status;
            editRideModalOverlay.classList.add('open');
        }

        function closeEditRideModal() {
            editRideModalOverlay.classList.remove('open');
        }

        document.getElementById('queue-table-body').addEventListener('click', function (e) {
            const btn = e.target.closest('.queue-edit-btn');
            if (!btn) return;
            openEditRideModal(btn.dataset.id, btn.dataset.status);
        });

        document.getElementById('editRideCancelBtn').addEventListener('click', closeEditRideModal);
        editRideModalOverlay.addEventListener('click', function (e) {
            if (e.target === editRideModalOverlay) closeEditRideModal();
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
                const response = await fetch(`/dashboard/rides/${rideId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        ride_id: rideId,
                        status: editRideStatusEl.value,
                    })
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
