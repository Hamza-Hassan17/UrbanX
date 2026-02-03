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
            --dark: #1f2937;
            --light: #f9fafb;
            --gray: #6b7280;
            --border: #e5e7eb;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .dashboard-container {
            /* display: grid;
                grid-template-columns: 380px 1fr;
                grid-template-rows: 80px 1fr;
                min-height: 100vh;
                gap: 0; */
            display: flex;
            flex-direction: column
        }

        /* Sidebar */
        .custom-sidebar {
            background: white;
            border-right: 1px solid var(--border);
            padding: 25px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary);
            width: 20px;
        }

        /* Driver Card */
        .driver-card {
            background: var(--light);
            border-radius: 16px;
            padding: 22px;
            border: 1px solid var(--border);
            transition: all 0.3s;
        }

        .driver-card:hover {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .driver-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .driver-avatar {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            position: relative;
        }

        .driver-status {
            position: absolute;
            bottom: -5px;
            right: -5px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 3px solid white;
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
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .driver-meta {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .driver-meta span {
            font-size: 14px;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .driver-meta i {
            width: 16px;
            color: var(--primary);
        }

        .driver-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: white;
            color: var(--primary);
            border: 1.5px solid var(--border);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            background: #f0f9ff;
        }

        /* Trip Form */
        .trip-form {
            margin-top: 10px;
            background: white;
            border-radius: 16px;
            padding: 22px;
            border: 1px solid var(--border);
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .input-with-icon input {
            padding-left: 46px;
        }

        /* Autocomplete Dropdown */
        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1.5px solid var(--border);
            border-top: none;
            border-radius: 0 0 10px 10px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .autocomplete-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s;
        }

        .autocomplete-item:hover {
            background-color: #f0f9ff;
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .location-icon {
            color: var(--primary);
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        .location-details {
            flex: 1;
        }

        .location-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--dark);
            margin-bottom: 2px;
        }

        .location-address {
            font-size: 12px;
            color: var(--gray);
        }

        .trip-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed var(--border);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
        }

        .stat-label {
            font-size: 12px;
            color: var(--gray);
            margin-top: 4px;
        }

        /* Loading spinner */
        .loading-spinner {
            text-align: center;
            padding: 10px;
            color: var(--gray);
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

        /* Main Content */
        .main-content {
            margin-top: 10px;
            /* padding: 25px; */
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 25px;
            /* overflow: hidden; */
        }

        /* Map Container */
        .map-container {
            flex: 1;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            position: relative;
            min-height: 500px;
        }

        #map {
            width: 100%;
            height: 100%;
            border-radius: 20px;
            z-index: 1;
            position: absolute !important;
        }

        .map-overlay {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: var(--card-shadow);
            width: 300px;
        }

        .map-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .map-controls {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .map-btn {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            border: 1.5px solid var(--border);
            background: white;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .map-btn:hover {
            border-color: var(--primary);
            background: #f0f9ff;
        }

        .map-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Trip List */
        .trips-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--card-shadow);
        }

        .trips-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .trips-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
        }

        .trip-count {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .trip-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .trip-card {
            border: 1.5px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            transition: all 0.3s;
        }

        .trip-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--card-shadow);
        }

        .trip-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .trip-id {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray);
            background: var(--light);
            padding: 4px 10px;
            border-radius: 20px;
        }

        .trip-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }

        .status-completed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .trip-route {
            margin-bottom: 20px;
        }

        .route-point {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .point-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .pickup-icon {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }

        .dropoff-icon {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .point-details h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
        }

        .point-details p {
            font-size: 13px;
            color: var(--gray);
            margin-top: 2px;
        }

        .trip-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px dashed var(--border);
        }

        .trip-driver {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .driver-small {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .driver-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
        }

        .trip-fare {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
        }

        /* Routing machine custom styles */
        .leaflet-routing-container {
            display: none;
            background: white;
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
            .dashboard-container {
                grid-template-columns: 1fr;
                grid-template-rows: 80px auto 1fr;
            }

            .custom-sidebar {
                grid-row: 2;
                grid-column: 1;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .main-content {
                grid-row: 3;
                grid-column: 1;
            }
        }

        @media (max-width: 768px) {
            .trip-list {
                grid-template-columns: 1fr;
            }

            .header {
                padding: 0 15px;
            }

            .time-display {
                display: none;
            }

            .map-overlay {
                width: 250px;
            }
        }

        /* Custom Leaflet Styles */
        .leaflet-control-zoom {
            border: none !important;
            box-shadow: var(--card-shadow) !important;
            border-radius: 10px !important;
            overflow: hidden;
        }

        .leaflet-control-zoom a {
            border-radius: 0 !important;
            border: none !important;
            width: 40px !important;
            height: 40px !important;
            line-height: 40px !important;
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
            background: white;
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
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- Driver Card -->
            <div class="driver-card">
                <div class="section-title">
                    <i class="fas fa-id-card"></i>
                    <span>Active Driver</span>
                </div>
                <div class="driver-header">
                    <div class="driver-avatar">
                        AA
                        <div class="driver-status status-online"></div>
                    </div>
                    <div class="driver-info">
                        <h3>Ali Ahmed</h3>
                        <div class="driver-meta">
                            <span><i class="fas fa-id-badge"></i> ID: DRV-7824</span>
                            <span><i class="fas fa-car"></i> Toyota Corolla</span>
                            <span><i class="fas fa-star"></i> Rating: 4.8/5.0</span>
                        </div>
                    </div>
                </div>
                <div class="driver-actions">
                    <button class="btn btn-primary" id="assign-trip">
                        <i class="fas fa-paper-plane"></i> Assign Trip
                    </button>
                    <button class="btn btn-outline" id="message-driver">
                        <i class="fas fa-comment"></i> Message
                    </button>
                </div>
            </div>

            <!-- Trip Details -->
            <div class="trip-form">
                <div class="section-title">
                    <i class="fas fa-route"></i>
                    <span>Trip Details</span>
                </div>
                <div class="form-group">
                    <label for="pickup-location"><i class="fas fa-map-marker-alt"></i> Pickup Location</label>
                    <div class="input-with-icon">
                        <i class="fas fa-location-dot"></i>
                        <input type="text" id="pickup-location" class="form-control"
                            placeholder="Start typing a location...">
                    </div>
                    <div class="autocomplete-dropdown" id="pickup-autocomplete"></div>
                </div>
                <div class="form-group">
                    <label for="destination"><i class="fas fa-flag-checkered"></i> Destination</label>
                    <div class="input-with-icon">
                        <i class="fas fa-location-dot"></i>
                        <input type="text" id="destination" class="form-control"
                            placeholder="Start typing a destination...">
                    </div>
                    <div class="autocomplete-dropdown" id="destination-autocomplete"></div>
                </div>
                <div class="form-group">
                    <label for="passenger-details"><i class="fas fa-user"></i> Passenger Details</label>
                    <input type="text" id="passenger-details" class="form-control" placeholder="Passenger name & phone"
                        value="Mohammad Khan • +92 300 1234567">
                </div>
                <div class="trip-stats">
                    <div class="stat-item">
                        <div class="stat-value" id="distance">0 km</div>
                        <div class="stat-label">Distance</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="time">0 min</div>
                        <div class="stat-label">Est. Time</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="fare">Rs 0</div>
                        <div class="stat-label">Est. Fare</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Map Container -->
            <div class="map-container">
                <div id="map"></div>
                {{-- <div class="map-overlay">
                    <div class="map-header">
                        <h3><i class="fas fa-map"></i> Live Fleet Map</h3>
                    </div>
                    <div class="map-controls">
                        <button class="map-btn active" id="show-all">
                            <i class="fas fa-taxi"></i> All
                        </button>
                        <button class="map-btn" id="show-available">
                            <i class="fas fa-circle-check"></i> Available
                        </button>
                        <button class="map-btn" id="show-busy">
                            <i class="fas fa-clock"></i> On Trip
                        </button>
                    </div>
                </div> --}}
            </div>

            <!-- Active Trips -->
            <div class="trips-container">
                <div class="trips-header">
                    <h3><i class="fas fa-list-check"></i> Active Rides</h3>
                    <span class="trip-count">{{ $activeRidesCount }} Rides</span>
                </div>
                <div class="trip-list">

                    @forelse($rides as $ride)
                        <div class="trip-card">
                            <div class="trip-card-header">
                                <span class="trip-id">RIDE-{{ $ride->id }}</span>

                                @php
                                    $activeStatuses = ['requested', 'accepted', 'en_route', 'arrived', 'started'];
                                @endphp

                                <span class="trip-status
                                    {{ in_array($ride->status, $activeStatuses) ? 'status-active' : 'status-pending' }}">
                                    {{ ucwords(str_replace('_', ' ', $ride->status)) }}
                                </span>
                            </div>

                            <div class="trip-route">
                                <div class="route-point">
                                    <div class="point-icon pickup-icon">
                                        <i class="fas fa-location-dot"></i>
                                    </div>
                                    <div class="point-details">
                                        <h4>Pickup Location</h4>
                                        <p id="pickup-{{ $ride->id }}">Loading pickup…</p>
                                        {{-- <p>{{ $ride->pickup_latitude }}, {{ $ride->pickup_longitude }}</p> --}}
                                    </div>
                                </div>

                                <div class="route-point">
                                    <div class="point-icon dropoff-icon">
                                        <i class="fas fa-flag-checkered"></i>
                                    </div>
                                    <div class="point-details">
                                        <h4>Dropoff Location</h4>
                                        <p id="dropoff-{{ $ride->id }}">Loading dropoff…</p>
                                        {{-- <p>{{ $ride->dropoff_latitude }}, {{ $ride->dropoff_longitude }}</p> --}}
                                    </div>
                                </div>
                            </div>

                            <div class="trip-footer">
                                <div class="trip-driver">
                                    <div class="driver-small">
                                        {{ strtoupper(substr($ride->driver->name ?? 'N/A', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="driver-name">
                                            {{ $ride->driver->name ?? 'Not Assigned' }}
                                        </div>
                                        <div style="font-size: 12px; color: var(--gray);">
                                            ETA: {{ $ride->duration_minutes ?? '--' }} min
                                        </div>
                                    </div>
                                </div>

                                <div class="trip-fare">
                                    Rs {{ number_format($ride->total_fare ?? 0) }}
                                </div>
                            </div>
                        </div>

                    @empty
                        <div style="padding: 20px; text-align: center; color: var(--gray);">
                            No active trips
                        </div>
                    @endforelse

                </div>

            </div>
        </main>
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

        const drivers = @json($drivers);

        // const drivers = [
        //     // DHA
        //     {
        //         id: 1,
        //         name: "Ali Ahmed",
        //         lat: 24.8135,
        //         lng: 67.0458,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2001",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 2,
        //         name: "Saad Khan",
        //         lat: 24.8019,
        //         lng: 67.0362,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2002",
        //         city: "Karachi"
        //     },

        //     // Clifton
        //     {
        //         id: 3,
        //         name: "Bilal Raza",
        //         lat: 24.8246,
        //         lng: 67.0329,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2003",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 4,
        //         name: "Usman Tariq",
        //         lat: 24.8191,
        //         lng: 67.0284,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2004",
        //         city: "Karachi"
        //     },

        //     // PECHS
        //     {
        //         id: 5,
        //         name: "Hassan Ali",
        //         lat: 24.8662,
        //         lng: 67.0701,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2005",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 6,
        //         name: "Fahad Iqbal",
        //         lat: 24.8604,
        //         lng: 67.0678,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2006",
        //         city: "Karachi"
        //     },

        //     // Saddar
        //     {
        //         id: 7,
        //         name: "Adeel Sheikh",
        //         lat: 24.8541,
        //         lng: 67.0219,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2007",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 8,
        //         name: "Shahzaib Noor",
        //         lat: 24.8576,
        //         lng: 67.0255,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2008",
        //         city: "Karachi"
        //     },

        //     // Gulshan-e-Iqbal
        //     {
        //         id: 9,
        //         name: "Imran Siddiqui",
        //         lat: 24.9184,
        //         lng: 67.0921,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2009",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 10,
        //         name: "Zeeshan Malik",
        //         lat: 24.9127,
        //         lng: 67.0993,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2010",
        //         city: "Karachi"
        //     },

        //     // Gulistan-e-Johar
        //     {
        //         id: 11,
        //         name: "Noman Aslam",
        //         lat: 24.9321,
        //         lng: 67.1286,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2011",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 12,
        //         name: "Salman Farooq",
        //         lat: 24.9254,
        //         lng: 67.1349,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2012",
        //         city: "Karachi"
        //     },

        //     // North Nazimabad
        //     {
        //         id: 13,
        //         name: "Rizwan Qureshi",
        //         lat: 24.9389,
        //         lng: 67.0442,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2013",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 14,
        //         name: "Kamran Akhtar",
        //         lat: 24.9453,
        //         lng: 67.0497,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2014",
        //         city: "Karachi"
        //     },

        //     // Nazimabad
        //     {
        //         id: 15,
        //         name: "Waqas Mehmood",
        //         lat: 24.9102,
        //         lng: 67.0326,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2015",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 16,
        //         name: "Arslan Baig",
        //         lat: 24.9047,
        //         lng: 67.0289,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2016",
        //         city: "Karachi"
        //     },

        //     // Korangi
        //     {
        //         id: 17,
        //         name: "Taimoor Latif",
        //         lat: 24.8426,
        //         lng: 67.1579,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2017",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 18,
        //         name: "Danish Rehman",
        //         lat: 24.8483,
        //         lng: 67.1512,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2018",
        //         city: "Karachi"
        //     },

        //     // Landhi
        //     {
        //         id: 19,
        //         name: "Shahbaz Khan",
        //         lat: 24.8361,
        //         lng: 67.1934,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2019",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 20,
        //         name: "Mubashir Ali",
        //         lat: 24.8298,
        //         lng: 67.1886,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2020",
        //         city: "Karachi"
        //     },

        //     // North Karachi
        //     {
        //         id: 21,
        //         name: "Asad Hussain",
        //         lat: 24.9754,
        //         lng: 67.0628,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2021",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 22,
        //         name: "Farhan Rafiq",
        //         lat: 24.9812,
        //         lng: 67.0704,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2022",
        //         city: "Karachi"
        //     },

        //     // Orangi Town
        //     {
        //         id: 23,
        //         name: "Adnan Yousuf",
        //         lat: 24.9526,
        //         lng: 67.0019,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2023",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 24,
        //         name: "Sohail Abbas",
        //         lat: 24.9478,
        //         lng: 66.9963,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2024",
        //         city: "Karachi"
        //     },

        //     // Malir
        //     {
        //         id: 25,
        //         name: "Hamza Nadeem",
        //         lat: 24.8937,
        //         lng: 67.1881,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2025",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 26,
        //         name: "Owais Anwar",
        //         lat: 24.8874,
        //         lng: 67.1956,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2026",
        //         city: "Karachi"
        //     },

        //     // SITE Area
        //     {
        //         id: 27,
        //         name: "Yasir Mahmood",
        //         lat: 24.8996,
        //         lng: 67.0124,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2027",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 28,
        //         name: "Ahsan Raza",
        //         lat: 24.9051,
        //         lng: 67.0189,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2028",
        //         city: "Karachi"
        //     },

        //     // Shah Faisal
        //     {
        //         id: 29,
        //         name: "Zubair Ahmed",
        //         lat: 24.8728,
        //         lng: 67.1453,
        //         status: "available",
        //         icon: taxiIcon,
        //         plate: "KHI-2029",
        //         city: "Karachi"
        //     },
        //     {
        //         id: 30,
        //         name: "Mohsin Javed",
        //         lat: 24.8674,
        //         lng: 67.1518,
        //         status: "busy",
        //         icon: taxiIconBusy,
        //         plate: "KHI-2030",
        //         city: "Karachi"
        //     },
        // ];


        // Add driver markers to map
        function addDriverMarkers() {
            drivers.forEach(driver => {

                const marker = L.marker(
                        [parseFloat(driver.lat), parseFloat(driver.lng)], {
                            icon: driver.status === 'available' ?
                                taxiIcon :
                                taxiIconBusy
                        }
                    )
                    .bindPopup(`
                    <div style="padding: 10px; min-width: 200px;">
                        <h3 style="margin: 0 0 10px 0; color: #1f2937;">${driver.name}</h3>
                        <p style="margin: 5px 0; font-size: 14px;"><strong>City:</strong> ${driver.city}</p>
                        <p style="margin: 5px 0; font-size: 14px;">
                            <strong>Status:</strong>
                            <span style="color: ${driver.status === 'available' ? '#10b981' : '#f59e0b'}">
                                ${driver.status === 'available' ? 'Available' : 'On Trip'}
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

        // Update pickup location on map
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

            // Listen for route found event
            routingControl.on('routesfound', function(e) {
                const routes = e.routes;
                if (routes && routes.length > 0) {
                    const route = routes[0];
                    const distance = (route.summary.totalDistance / 1000).toFixed(1); // Convert to km
                    const time = Math.round(route.summary.totalTime / 60); // Convert to minutes
                    const fare = Math.round(100 + distance * 25); // Calculate fare

                    // Update UI
                    document.getElementById('distance').textContent = `${distance} km`;
                    document.getElementById('time').textContent = `${time} min`;
                    document.getElementById('fare').textContent = `Rs ${fare}`;

                    // Fit map to show route
                    const bounds = L.latLngBounds([
                        [pickupCoordinates[0], pickupCoordinates[1]],
                        [destinationCoordinates[0], destinationCoordinates[1]]
                    ]);
                    map.fitBounds(bounds.pad(0.1));
                }
            });

            routingControl.on('routingerror', function(e) {
                showNotification('Could not calculate route. Using straight line distance.', 'warning');
                calculateFallbackDistance();
            });
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

            // Map filter controls
            document.getElementById('show-all').addEventListener('click', function() {
                setActiveButton(this);
                // In a real app, you would filter markers here
            });

            document.getElementById('show-available').addEventListener('click', function() {
                setActiveButton(this);
                // In a real app, you would filter markers here
            });

            document.getElementById('show-busy').addEventListener('click', function() {
                setActiveButton(this);
                // In a real app, you would filter markers here
            });

            function setActiveButton(button) {
                document.querySelectorAll('.map-btn').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
            }

            // Assign trip button
            document.getElementById('assign-trip').addEventListener('click', function() {
                const driverName = document.querySelector('.driver-info h3').textContent;
                const pickup = document.getElementById('pickup-location').value;
                const destination = document.getElementById('destination').value;

                if (!pickup || !destination) {
                    showNotification('Please select both pickup and destination locations first!', 'error');
                    return;
                }

                if (!pickupCoordinates || !destinationCoordinates) {
                    showNotification('Please select valid locations from the suggestions', 'error');
                    return;
                }

                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
                this.disabled = true;

                // Simulate API call
                setTimeout(() => {
                    this.innerHTML = '<i class="fas fa-check"></i> Assigned!';
                    this.style.backgroundColor = '#10b981';

                    // Add new trip to the list
                    addNewTrip(driverName, pickup, destination);

                    // Reset after 2 seconds
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.style.backgroundColor = '';
                        this.disabled = false;
                    }, 2000);
                }, 1500);
            });

            // Message driver button
            document.getElementById('message-driver').addEventListener('click', function() {
                const driverName = document.querySelector('.driver-info h3').textContent;
                showNotification(`Opening WhatsApp chat with ${driverName}...`, 'info');
            });
        });

        // Assign driver function
        window.assignDriver = function(driverId) {
            const driver = drivers.find(d => d.id === driverId);
            if (driver) {
                // Update driver card
                document.querySelector('.driver-avatar').textContent = driver.name.split(' ').map(n => n[0]).join('');
                document.querySelector('.driver-info h3').textContent = driver.name;
                document.querySelector('.driver-meta span:first-child').innerHTML =
                    `<i class="fas fa-id-badge"></i> ID: ${driver.plate}`;

                showNotification(`Assigned ${driver.name} from ${driver.city} to the current trip!`, 'success');
            }
        };

        // Add new trip to the list
        function addNewTrip(driverName, pickup, destination) {
            const tripList = document.querySelector('.trip-list');
            const tripCount = document.querySelector('.trip-count');

            // Create new trip card
            const newTrip = document.createElement('div');
            newTrip.className = 'trip-card';
            newTrip.innerHTML = `
                <div class="trip-card-header">
                    <span class="trip-id">TRIP-4893</span>
                    <span class="trip-status status-active">Active</span>
                </div>
                <div class="trip-route">
                    <div class="route-point">
                        <div class="point-icon pickup-icon">
                            <i class="fas fa-location-dot"></i>
                        </div>
                        <div class="point-details">
                            <h4>Pickup: ${pickup.split(',')[0]}</h4>
                            <p>Just now • ${pickup.substring(0, 50)}...</p>
                        </div>
                    </div>
                    <div class="route-point">
                        <div class="point-icon dropoff-icon">
                            <i class="fas fa-flag-checkered"></i>
                        </div>
                        <div class="point-details">
                            <h4>Dropoff: ${destination.split(',')[0]}</h4>
                            <p>${destination.substring(0, 50)}...</p>
                        </div>
                    </div>
                </div>
                <div class="trip-footer">
                    <div class="trip-driver">
                        <div class="driver-small">${driverName.split(' ').map(n => n[0]).join('')}</div>
                        <div>
                            <div class="driver-name">${driverName}</div>
                            <div style="font-size: 12px; color: var(--gray);">ETA: ${document.getElementById('time').textContent}</div>
                        </div>
                    </div>
                    <div class="trip-fare">${document.getElementById('fare').textContent}</div>
                </div>
            `;

            // Insert at the beginning
            tripList.insertBefore(newTrip, tripList.firstChild);

            // Update trip count
            const currentCount = parseInt(tripCount.textContent);
            tripCount.textContent = `${currentCount + 1} Trips`;

            showNotification('Trip assigned successfully!', 'success');
        }

        function reverseGeocode(lat, lng, elementId) {
            if (!lat || !lng) {
                document.getElementById(elementId).innerText = 'Location unavailable';
                return;
            }

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.display_name) {
                        document.getElementById(elementId).innerText = data.display_name;
                    } else {
                        document.getElementById(elementId).innerText = 'Location unavailable';
                    }
                })
                .catch(err => {
                    console.error('Reverse geocoding error:', err);
                    document.getElementById(elementId).innerText = 'Location unavailable';
                });
        }


        reverseGeocode({{ $ride->pickup_latitude }}, {{ $ride->pickup_longitude }}, 'pickup-{{ $ride->id }}');
        reverseGeocode({{ $ride->dropoff_latitude }}, {{ $ride->dropoff_longitude }}, 'dropoff-{{ $ride->id }}');
    </script>
@endsection
