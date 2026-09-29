@extends('layouts.master')

@section('title', __('Live Tracking'))

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

        @keyframes anomaly-pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.6); }
            70% { box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
        }

        /* Map panel -- full page here, not one column of the dispatch grid */
        .map-panel {
            padding: 0;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
            min-height: 78vh;
        }

        .live-tracking-bar {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border, #e5e7eb);
            flex-shrink: 0;
        }

        #map {
            position: relative;
            width: 100%;
            flex: 1;
            z-index: 1;
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
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
@endsection

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Live Tracking') }}</li>
@endsection

@section('content')
    <div class="dashboard-container">
        <section class="panel map-panel" id="live-tracking">
            <div class="live-tracking-bar">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <label for="cityFilter" class="mb-0 text-muted" style="font-size: 13px;">City:</label>
                    <select id="cityFilter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All Cities</option>
                        <option value="unassigned">Unassigned</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city }}">{{ $city }}</option>
                        @endforeach
                    </select>
                    <div class="form-check form-switch ms-2">
                        <input class="form-check-input" type="checkbox" id="activeOnlyToggle">
                        <label class="form-check-label" for="activeOnlyToggle" style="font-size: 13px;">
                            Active rides/deliveries only
                        </label>
                    </div>
                    <small class="text-muted ms-auto" style="font-size: 12px;">
                        <i class="fas fa-circle" style="color:#10b981; font-size:8px;"></i> Available
                        <i class="fas fa-circle ms-2" style="color:#f59e0b; font-size:8px;"></i> Busy
                        <i class="fas fa-circle ms-2" style="color:#2563eb; font-size:8px;"></i> Active taxi ride (last known position)
                        <i class="fas fa-circle ms-2" style="color:#db2777; font-size:8px;"></i> Active delivery (live)
                    </small>
                </div>
            </div>
            <div id="map"></div>
        </section>
    </div>
@endsection

@section('script')
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}"></script>
    <script>
        let map;
        let cityFilter = 'all';
        let activeOnly = false;
        let driverMarkers = [];
        let activeRideMarkers = [];
        let activeDeliveryMarkers = [];

        function initMap() {
            map = new google.maps.Map(document.getElementById('map'), {
                center: { lat: 30.3753, lng: 69.3451 },
                zoom: 6,
            });

            addDriverMarkers();
        }

        // Colored-circle SVG marker icon, optionally with a short text label
        // and/or a pulsing animation (via SMIL <animate>, which plays even
        // inside an <img> showing an SVG data URI).
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

        // Custom taxi icons
        const taxiIcon = svgCircleIcon({ color: '#10b981', label: 'T' });
        const taxiIconBusy = svgCircleIcon({ color: '#f59e0b', label: 'T' });

        // Active-ride / active-delivery icons (Live Ops Task 3 -- distinct from
        // idle available/busy driver markers above).
        const activeRideIcon = svgCircleIcon({ color: '#2563eb', size: 40, label: 'R' });
        const activeDeliveryIcon = svgCircleIcon({ color: '#db2777', size: 40, label: 'D' });

        // Anomaly (Live Ops Task 4) -- wrong-direction or stale-GPS driver,
        // shown red and pulsing so it stands out from the normal blue/pink
        // active markers above.
        const anomalyIcon = svgCircleIcon({ color: '#dc2626', label: '!', pulse: true, bg: '#fee2e2' });

        const anomalyLabels = {
            wrong_direction: 'Wrong direction',
            stale_gps: 'GPS stalled 4+ min',
        };

        function getDriverIcon(driver) {
            const borderColor = driver.status === 'available' ? '#10b981' : '#f59e0b';
            return svgCircleIcon({ color: borderColor, label: 'D-' + driver.id });
        }

        const drivers = @json($drivers);

        function driverMatchesCityFilter(driver) {
            if (cityFilter === 'all') return true;
            if (cityFilter === 'unassigned') return !driver.city;
            return driver.city === cityFilter;
        }

        // Add driver markers to map
        function addDriverMarkers() {
            driverMarkers.forEach(m => m.setMap(null));
            driverMarkers = [];

            if (activeOnly) return; // idle drivers hidden while "active only" is on

            drivers.forEach(driver => {

                const lat = parseFloat(driver.lat);
                const lng = parseFloat(driver.lng);

                // Skip invalid coordinates – very important!
                if (isNaN(lat) || isNaN(lng)) {
                    console.warn(`Driver ${driver.id} (${driver.name}) has invalid coordinates: lat=${driver.lat}, lng=${driver.lng}`);
                    return;
                }

                if (!driverMatchesCityFilter(driver)) return;

                const marker = new google.maps.Marker({
                    position: { lat, lng },
                    map: map,
                    icon: getDriverIcon(driver),
                });
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                    <div style="padding: 10px; min-width: 200px;">
                        <h3 style="margin: 0 0 10px 0; color: #1f2937;">${driver.name}</h3>
                        <p style="margin: 5px 0; font-size: 14px;"><strong>#ID:</strong> ${driver.id}</p>
                        <p style="margin: 5px 0; font-size: 14px;"><strong>City:</strong> ${driver.city ?? 'Unassigned'}</p>
                        <p style="margin: 5px 0; font-size: 14px;">
                            <strong>Status:</strong>
                            <span style="color: ${driver.status === 'available' ? '#10b981' : '#f59e0b'}">
                                ${driver.status === 'available' ? 'Available' : 'Busy'}
                            </span>
                        </p>
                        <p style="margin: 5px 0; font-size: 14px;"><strong>Vehicle:</strong> ${driver.vehicle ?? 'N/A'}</p>
                        <p style="margin: 8px 0 0 0; font-size: 12px; color: #9ca3af;">
                            <i class="fas fa-info-circle"></i> To assign this driver, go to Manual Ride Assignment.
                        </p>
                    </div>
                `,
                });
                marker.addListener('click', () => infoWindow.open(map, marker));
                driverMarkers.push(marker);
            });
        }

        // City lookup by driver id -- active-ride/delivery rows don't carry city
        // themselves, so filtering them by city means looking up their driver.
        function cityForDriverId(driverId) {
            const d = drivers.find(d => d.id == driverId);
            return d ? d.city : null;
        }

        function renderActiveRideMarkers(activeRides) {
            activeRideMarkers.forEach(m => m.setMap(null));
            activeRideMarkers = [];

            activeRides.forEach(ride => {
                if (cityFilter !== 'all') {
                    const city = cityForDriverId(ride.driver_id);
                    const matches = cityFilter === 'unassigned' ? !city : city === cityFilter;
                    if (!matches) return;
                }

                const hasAnomaly = ride.anomalies && ride.anomalies.length > 0;
                const anomalyHtml = hasAnomaly
                    ? `<p style="margin: 8px 0 0 0; font-size: 13px; color: #dc2626; font-weight: 600;">
                            <i class="fas fa-triangle-exclamation"></i> ${ride.anomalies.map(a => anomalyLabels[a] ?? a).join(', ')}
                       </p>`
                    : '';

                const marker = new google.maps.Marker({
                    position: { lat: ride.lat, lng: ride.lng },
                    map: map,
                    icon: hasAnomaly ? anomalyIcon : activeRideIcon,
                });
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="padding: 10px; min-width: 200px;">
                            <h3 style="margin: 0 0 10px 0; color: #1f2937;">Active Ride #${ride.ride_id}</h3>
                            <p style="margin: 5px 0; font-size: 14px;"><strong>Driver:</strong> ${ride.driver_name ?? 'N/A'}</p>
                            <p style="margin: 5px 0; font-size: 14px;"><strong>Passenger:</strong> ${ride.passenger_name ?? 'N/A'}</p>
                            <p style="margin: 5px 0; font-size: 14px;"><strong>Status:</strong> ${ride.status}</p>
                            <p style="margin: 5px 0; font-size: 14px;"><strong>Pickup:</strong> ${ride.pickup}</p>
                            <p style="margin: 5px 0; font-size: 14px;"><strong>Dropoff:</strong> ${ride.dropoff}</p>
                            <p style="margin: 8px 0 0 0; font-size: 12px; color: #9ca3af;">
                                <i class="fas fa-info-circle"></i> Last known driver position, not continuous GPS.
                            </p>
                            ${anomalyHtml}
                        </div>
                    `,
                });
                marker.addListener('click', () => infoWindow.open(map, marker));
                activeRideMarkers.push(marker);
            });
        }

        function renderActiveDeliveryMarkers(activeDeliveries) {
            activeDeliveryMarkers.forEach(m => m.setMap(null));
            activeDeliveryMarkers = [];

            activeDeliveries.forEach(delivery => {
                const hasAnomaly = delivery.anomalies && delivery.anomalies.length > 0;
                const anomalyHtml = hasAnomaly
                    ? `<p style="margin: 8px 0 0 0; font-size: 13px; color: #dc2626; font-weight: 600;">
                            <i class="fas fa-triangle-exclamation"></i> ${delivery.anomalies.map(a => anomalyLabels[a] ?? a).join(', ')}
                       </p>`
                    : '';

                const marker = new google.maps.Marker({
                    position: { lat: delivery.lat, lng: delivery.lng },
                    map: map,
                    icon: hasAnomaly ? anomalyIcon : activeDeliveryIcon,
                });
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="padding: 10px; min-width: 200px;">
                            <h3 style="margin: 0 0 10px 0; color: #1f2937;">Active Delivery — Order #${delivery.order_id}</h3>
                            <p style="margin: 5px 0; font-size: 14px;"><strong>Restaurant:</strong> ${delivery.restaurant_name ?? 'N/A'}</p>
                            <p style="margin: 5px 0; font-size: 14px;"><strong>Customer:</strong> ${delivery.customer_name ?? 'N/A'}</p>
                            <p style="margin: 5px 0; font-size: 14px;"><strong>Status:</strong> ${delivery.status}</p>
                            <p style="margin: 5px 0; font-size: 14px;"><strong>Pickup:</strong> ${delivery.pickup ?? 'N/A'}</p>
                            <p style="margin: 5px 0; font-size: 14px;"><strong>Dropoff:</strong> ${delivery.dropoff ?? 'N/A'}</p>
                            <p style="margin: 8px 0 0 0; font-size: 12px; color: #9ca3af;">
                                <i class="fas fa-satellite-dish"></i> Live position.
                            </p>
                            ${anomalyHtml}
                        </div>
                    `,
                });
                marker.addListener('click', () => infoWindow.open(map, marker));
                activeDeliveryMarkers.push(marker);
            });
        }

        async function fetchLiveTrackingData() {
            try {
                const response = await fetch(@json(route('dashboard.custom-rides.live-tracking')), {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) return;
                const data = await response.json();
                renderActiveRideMarkers(data.activeRides || []);
                renderActiveDeliveryMarkers(data.activeDeliveries || []);
            } catch (error) {
                console.error('Live tracking refresh failed:', error);
            }
        }

        document.getElementById('cityFilter')?.addEventListener('change', function () {
            cityFilter = this.value;
            addDriverMarkers();
            fetchLiveTrackingData();
        });

        document.getElementById('activeOnlyToggle')?.addEventListener('change', function () {
            activeOnly = this.checked;
            addDriverMarkers();
        });

        document.addEventListener('DOMContentLoaded', function() {
            initMap();

            // Live trace refresh -- separate interval since this hits the
            // active-rides/deliveries query on every tick; 8s keeps that cost
            // reasonable without the map feeling stale.
            fetchLiveTrackingData();
            setInterval(fetchLiveTrackingData, 8000);
        });
    </script>
@endsection
