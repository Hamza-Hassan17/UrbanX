@extends('layouts.master')

@section('title', __('Ride Details'))

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .ride-status {
        font-size: 0.9rem;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-weight: 600;
    }
    .status-requested { background-color: #e3f2fd; color: #1565c0; }
    .status-accepted { background-color: #fff3e0; color: #ef6c00; }
    .status-en_route { background-color: #fff8e1; color: #ff8f00; }
    .status-arrived { background-color: #e8f5e9; color: #2e7d32; }
    .status-started { background-color: #e8f5e9; color: #1b5e20; }
    .status-completed { background-color: #e8f5e9; color: #1b5e20; }
    .status-cancelled { background-color: #ffebee; color: #c62828; }

    .info-card {
        border-left: 4px solid #4e54c8;
    }
    .info-label {
        font-size: 0.875rem;
        color: #6c757d;
        font-weight: 500;
    }
    .info-value {
        font-size: 1rem;
        color: #212529;
        font-weight: 600;
    }
    #map {
        height: 400px;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    .map-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 400px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    .address-loading {
        color: #6c757d;
        font-style: italic;
    }
    .extra-charge-badge {
        font-size: 0.75rem;
        padding: 0.2rem 0.5rem;
    }
    .boost-hour-badge {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }
</style>
@endsection

@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.rides.index') }}">{{ __('Rides') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Ride Details') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="row">
            <div class="col-lg-8">
                <!-- Ride Header -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Ride #{{ $ride->id }}</h5>
                            <span class="text-muted">Requested: {{ \Carbon\Carbon::parse($ride->requested_at)->format('M d, Y h:i A') }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if($boostHour)
                            <span class="badge boost-hour-badge" data-bs-toggle="tooltip" data-bs-placement="top"
                                  title="Boost Hour: {{ $boostHour->start }} - {{ $boostHour->end }} ({{ $boostHour->multiplier }}x)">
                                <i class="bx bx-trending-up me-1"></i> {{ $boostHour->multiplier }}x Boost
                            </span>
                            @endif
                            <span class="ride-status status-{{ $ride->status }}">
                                {{ ucfirst(str_replace('_', ' ', $ride->status)) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Location & Route Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Route Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <div class="info-card p-3 bg-light">
                                    <h6 class="info-label">Pickup Location</h6>
                                    <p class="info-value mb-1" id="pickup-address">
                                        <span class="address-loading">Loading address...</span>
                                    </p>
                                    <small class="text-muted">Coordinates: {{ $ride->pickup_latitude }}, {{ $ride->pickup_longitude }}</small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                @if($ride->dropoff_latitude)
                                <div class="info-card p-3 bg-light">
                                    <h6 class="info-label">Dropoff Location</h6>
                                    <p class="info-value mb-1" id="dropoff-address">
                                        <span class="address-loading">Loading address...</span>
                                    </p>
                                    <small class="text-muted">Coordinates: {{ $ride->dropoff_latitude }}, {{ $ride->dropoff_longitude }}</small>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Map Container -->
                        <div id="map" class="mb-4"></div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center p-2">
                                    <h6 class="info-label">Distance</h6>
                                    <p class="info-value">{{ $ride->distance_km ?? '0.00' }} km</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-2">
                                    <h6 class="info-label">Duration</h6>
                                    <p class="info-value">{{ $ride->duration_minutes ?? '0' }} minutes</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-2">
                                    <h6 class="info-label">Vehicle Type</h6>
                                    <p class="info-value">{{ $ride->vehicleType->name ?? 'N/A' }}</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-2">
                                    <h6 class="info-label">Request Time</h6>
                                    <p class="info-value">{{ \Carbon\Carbon::parse($ride->requested_at)->format('h:i A') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ride Offers -->
                @if($rideOffers->count() > 0)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Ride Offers ({{ $rideOffers->count() }})</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Driver</th>
                                        <th>Proposed Price</th>
                                        <th>ETA</th>
                                        <th>Status</th>
                                        <th>Offered At</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rideOffers as $offer)
                                    <tr>
                                        <td>
                                            <div>
                                                <strong>{{ $offer->driver->name ?? 'N/A' }}</strong><br>
                                                <small>{{ $offer->driver->phone ?? '' }}</small>
                                            </div>
                                        </td>
                                        <td>{{ \App\Helpers\Helper::formatCurrency($offer->proposed_price) }}</td>
                                        <td>{{ $offer->eta_minutes }} minutes</td>
                                        <td>
                                            <span class="badge bg-{{ $offer->status == 'accepted' ? 'success' : ($offer->status == 'rejected' ? 'danger' : ($offer->status == 'expired' ? 'warning' : 'info')) }}">
                                                {{ ucfirst($offer->status) }}
                                            </span>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($offer->offered_at)->format('M d, Y h:i A') }}</td>
                                        <td>{{ $offer->note ?? '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Extra Charges -->
                @if($rideExtraCharges->count() > 0)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Extra Charges ({{ $rideExtraCharges->count() }})</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Charge Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Applied At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rideExtraCharges as $extraCharge)
                                    <tr>
                                        <td>
                                            <span class="badge extra-charge-badge bg-label-{{
                                                $extraCharge->charge_type == 'toll' ? 'info' :
                                                ($extraCharge->charge_type == 'waiting' ? 'warning' :
                                                ($extraCharge->charge_type == 'cleaning' ? 'danger' :
                                                ($extraCharge->charge_type == 'peak_hour' ? 'success' : 'secondary')))
                                            }}">
                                                {{ ucfirst(str_replace('_', ' ', $extraCharge->charge_type)) }}
                                            </span>
                                        </td>
                                        <td>
                                            @php
                                                $descriptions = [
                                                    'toll' => 'Toll road charges',
                                                    'waiting' => 'Waiting time charges',
                                                    'cleaning' => 'Cleaning fee',
                                                    'peak_hour' => 'Peak hour surcharge',
                                                    'night_charge' => 'Night time surcharge',
                                                    'airport' => 'Airport pickup/dropoff fee'
                                                ];
                                            @endphp
                                            {{ $descriptions[$extraCharge->charge_type] ?? 'Additional charge' }}
                                        </td>
                                        <td>{{ \App\Helpers\Helper::formatCurrency($extraCharge->amount) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($extraCharge->created_at)->format('M d, Y h:i A') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                @if($rideExtraCharges->count() > 0)
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>Total Extra Charges:</strong></td>
                                        <td colspan="2">
                                            <strong>{{ \App\Helpers\Helper::formatCurrency($rideExtraCharges->sum('amount')) }}</strong>
                                        </td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <div class="col-lg-4">
                <!-- Passenger Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Passenger Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar avatar-md me-3">
                                <span class="avatar-initial rounded-circle bg-label-primary">
                                    {{ substr($ride->passenger->name ?? 'P', 0, 1) }}
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ $ride->passenger->name ?? 'N/A' }}</h6>
                                <small>{{ $ride->passenger->phone ?? '' }}</small>
                            </div>
                        </div>
                        <div class="mb-2">
                            <span class="info-label">Email:</span>
                            <span class="info-value">{{ $ride->passenger->email ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Driver Information -->
                @if($ride->driver)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Driver Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar avatar-md me-3">
                                <span class="avatar-initial rounded-circle bg-label-success">
                                    {{ substr($ride->driver->name ?? 'D', 0, 1) }}
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ $ride->driver->name ?? 'N/A' }}</h6>
                                <small>{{ $ride->driver->phone ?? '' }}</small>
                            </div>
                        </div>
                        <div class="mb-2">
                            <span class="info-label">Email:</span>
                            <span class="info-value">{{ $ride->driver->email ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Vehicle Type Details -->
                @if($ride->vehicleType)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Vehicle Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <span class="info-label">Type:</span>
                            <span class="info-value">{{ $ride->vehicleType->name }}</span>
                        </div>
                        @if($ride->vehicleType->seats)
                        <div class="mb-2">
                            <span class="info-label">Seats:</span>
                            <span class="info-value">{{ $ride->vehicleType->seats }}</span>
                        </div>
                        @endif
                        @if($ride->vehicleType->base_fare)
                        <div class="mb-2">
                            <span class="info-label">Base Fare:</span>
                            <span class="info-value">{{ \App\Helpers\Helper::formatCurrency($ride->vehicleType->base_fare) }}</span>
                        </div>
                        @endif
                        @if($ride->vehicleType->first_km_price)
                        <div class="mb-2">
                            <span class="info-label">First KM Price:</span>
                            <span class="info-value">{{ \App\Helpers\Helper::formatCurrency($ride->vehicleType->first_km_price) }}</span>
                        </div>
                        @endif
                        @if($ride->vehicleType->other_km_price)
                        <div class="mb-2">
                            <span class="info-label">Other KM Price:</span>
                            <span class="info-value">{{ \App\Helpers\Helper::formatCurrency($ride->vehicleType->other_km_price) }}</span>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <!-- Fare Breakdown -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Fare Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <!-- Base Calculation -->
                        @if($ride->distance_km && $ride->vehicleType)
                        <div class="mb-2">
                            <span class="info-label">Base Calculation:</span>
                            <div class="ms-3">
                                @if($ride->vehicleType->base_fare)
                                <div class="d-flex justify-content-between">
                                    <small>Base Fare:</small>
                                    <small>{{ \App\Helpers\Helper::formatCurrency($ride->vehicleType->base_fare) }}</small>
                                </div>
                                @endif
                                @if($ride->vehicleType->first_km_price)
                                <div class="d-flex justify-content-between">
                                    <small>First KM (1km):</small>
                                    <small>{{ \App\Helpers\Helper::formatCurrency($ride->vehicleType->first_km_price) }}</small>
                                </div>
                                @endif
                                @if($ride->distance_km > 1 && $ride->vehicleType->other_km_price)
                                <div class="d-flex justify-content-between">
                                    <small>Additional {{ number_format($ride->distance_km - 1, 2) }} km:</small>
                                    <small>{{ \App\Helpers\Helper::formatCurrency(($ride->distance_km - 1) * $ride->vehicleType->other_km_price) }}</small>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        <div class="d-flex justify-content-between mb-2">
                            <span class="info-label">Subtotal:</span>
                            <span class="info-value">{{ \App\Helpers\Helper::formatCurrency($ride->subtotal) }}</span>
                        </div>

                        <!-- Boost Hour Multiplier -->
                        @if($boostHour)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="info-label">
                                Boost Hour ({{ $boostHour->multiplier }}x):
                                <small class="text-muted d-block">{{ $boostHour->start }} - {{ $boostHour->end }}</small>
                            </span>
                            <span class="info-value text-warning">
                                × {{ $boostHour->multiplier }}
                            </span>
                        </div>
                        @endif

                        @if($ride->discount_amount > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="info-label">Discount:</span>
                            <span class="info-value text-danger">-{{ \App\Helpers\Helper::formatCurrency($ride->discount_amount) }}</span>
                        </div>
                        @endif

                        <!-- Extra Charges Summary -->
                        @if($rideExtraCharges->count() > 0)
                        <div class="mb-2">
                            <span class="info-label">Extra Charges:</span>
                            <div class="ms-3">
                                @foreach($rideExtraCharges as $extraCharge)
                                <div class="d-flex justify-content-between">
                                    <small>{{ ucfirst(str_replace('_', ' ', $extraCharge->charge_type)) }}:</small>
                                    <small>+{{ \App\Helpers\Helper::formatCurrency($extraCharge->amount) }}</small>
                                </div>
                                @endforeach
                                <div class="d-flex justify-content-between">
                                    <small><strong>Total Extra:</strong></small>
                                    <small><strong>+{{ \App\Helpers\Helper::formatCurrency($rideExtraCharges->sum('amount')) }}</strong></small>
                                </div>
                            </div>
                        </div>
                        @endif

                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="info-label fw-bold">Total Fare:</span>
                            <span class="info-value fw-bold fs-5">{{ \App\Helpers\Helper::formatCurrency($ride->total_fare) }}</span>
                        </div>

                        @if($ride->promoCode)
                        <hr class="my-2">
                        <div class="mt-2">
                            <span class="info-label">Promo Code:</span>
                            <span class="badge bg-label-info">{{ $ride->promoCode->code }}</span>
                            <small class="d-block text-muted">{{ $ride->promoCode->name }} ({{ $ride->promoCode->discount_percentage }}% off)</small>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Ride Timeline -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Ride Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Requested</h6>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($ride->requested_at)->format('M d, Y h:i A') }}</small>
                                </div>
                            </div>

                            @if($ride->accepted_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Accepted</h6>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($ride->accepted_at)->format('M d, Y h:i A') }}</small>
                                </div>
                            </div>
                            @endif

                            @if($ride->started_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-info"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Started</h6>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($ride->started_at)->format('M d, Y h:i A') }}</small>
                                </div>
                            </div>
                            @endif

                            @if($ride->completed_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Completed</h6>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($ride->completed_at)->format('M d, Y h:i A') }}</small>
                                </div>
                            </div>
                            @endif

                            @if($ride->cancelled_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-danger"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Cancelled</h6>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($ride->cancelled_at)->format('M d, Y h:i A') }}</small>
                                    @if($ride->cancel_reason)
                                    <p class="mb-0"><small>Reason: {{ $ride->cancel_reason }}</small></p>
                                    @endif
                                    @if($ride->status_updated_by_role === 'admin' && $ride->statusUpdatedBy)
                                    <p class="mb-0"><small>Cancelled by admin: {{ $ride->statusUpdatedBy->name }}</small></p>
                                    @elseif($ride->status_updated_by_role === 'passenger')
                                    <p class="mb-0"><small>Cancelled by the passenger</small></p>
                                    @elseif($ride->status_updated_by_role === 'driver')
                                    <p class="mb-0"><small>Cancelled by the driver</small></p>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Add timeline styling
        const style = document.createElement('style');
        style.textContent = `
            .timeline {
                position: relative;
                padding-left: 20px;
            }
            .timeline-item {
                position: relative;
                padding-bottom: 20px;
            }
            .timeline-marker {
                position: absolute;
                left: -26px;
                top: 0;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                border: 2px solid white;
                box-shadow: 0 0 0 3px var(--bs-primary);
            }
            .timeline-content {
                padding-left: 10px;
            }
            .timeline-item:not(:last-child):before {
                content: '';
                position: absolute;
                left: -20px;
                top: 12px;
                bottom: 0;
                width: 2px;
                background-color: #e0e0e0;
            }
            .timeline-marker.bg-primary { box-shadow: 0 0 0 3px var(--bs-primary); }
            .timeline-marker.bg-success { box-shadow: 0 0 0 3px var(--bs-success); }
            .timeline-marker.bg-info { box-shadow: 0 0 0 3px var(--bs-info); }
            .timeline-marker.bg-danger { box-shadow: 0 0 0 3px var(--bs-danger); }
        `;
        document.head.appendChild(style);

        // Ride coordinates
        const rideData = {
            pickup: {
                lat: {{ $ride->pickup_latitude }},
                lng: {{ $ride->pickup_longitude }}
            },
            @if($ride->dropoff_latitude)
            dropoff: {
                lat: {{ $ride->dropoff_latitude }},
                lng: {{ $ride->dropoff_longitude }}
            }
            @endif
        };

        // Initialize map
        let map;
        let pickupMarker, dropoffMarker;

        function initMap() {
            // Calculate center point
            let centerLat, centerLng;
            if (rideData.dropoff) {
                centerLat = (rideData.pickup.lat + rideData.dropoff.lat) / 2;
                centerLng = (rideData.pickup.lng + rideData.dropoff.lng) / 2;
            } else {
                centerLat = rideData.pickup.lat;
                centerLng = rideData.pickup.lng;
            }

            // Initialize map
            map = L.map('map').setView([centerLat, centerLng], 13);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // Create custom icons
            const pickupIcon = L.divIcon({
                html: '<div style="background-color: #4e54c8; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="bx bx-flag" style="color: white; font-size: 12px;"></i></div>',
                className: 'custom-pickup-icon',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            const dropoffIcon = L.divIcon({
                html: '<div style="background-color: #dc3545; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="bx bx-target-lock" style="color: white; font-size: 12px;"></i></div>',
                className: 'custom-dropoff-icon',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            // Add pickup marker
            pickupMarker = L.marker([rideData.pickup.lat, rideData.pickup.lng], {
                icon: pickupIcon
            }).addTo(map).bindPopup('<strong>Pickup Location</strong>');

            // Add dropoff marker if exists
            if (rideData.dropoff) {
                dropoffMarker = L.marker([rideData.dropoff.lat, rideData.dropoff.lng], {
                    icon: dropoffIcon
                }).addTo(map).bindPopup('<strong>Dropoff Location</strong>');

                // Draw line between points
                const polyline = L.polyline([
                    [rideData.pickup.lat, rideData.pickup.lng],
                    [rideData.dropoff.lat, rideData.dropoff.lng]
                ], {
                    color: '#4e54c8',
                    weight: 3,
                    opacity: 0.7,
                    dashArray: '5, 10'
                }).addTo(map);

                // Fit bounds to show both markers
                const bounds = L.latLngBounds(
                    [rideData.pickup.lat, rideData.pickup.lng],
                    [rideData.dropoff.lat, rideData.dropoff.lng]
                );
                map.fitBounds(bounds, { padding: [50, 50] });
            } else {
                // Zoom to pickup if no dropoff
                map.setView([rideData.pickup.lat, rideData.pickup.lng], 15);
            }
        }

        // Function to reverse geocode coordinates
        async function reverseGeocode(lat, lng) {
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
                const data = await response.json();

                if (data && data.display_name) {
                    return data.display_name;
                }
                return 'Address not found';
            } catch (error) {
                console.error('Geocoding error:', error);
                return 'Unable to fetch address';
            }
        }

        // Update addresses on page load
        async function updateAddresses() {
            // Get pickup address
            const pickupAddress = await reverseGeocode(rideData.pickup.lat, rideData.pickup.lng);
            document.getElementById('pickup-address').innerHTML = pickupAddress;

            // Get dropoff address if exists
            if (rideData.dropoff) {
                const dropoffAddress = await reverseGeocode(rideData.dropoff.lat, rideData.dropoff.lng);
                document.getElementById('dropoff-address').innerHTML = dropoffAddress;
            } else {
                document.getElementById('dropoff-address').innerHTML = 'No dropoff location specified';
            }
        }

        // Initialize map and addresses
        initMap();
        updateAddresses();
    });
</script>
@endsection
