@extends('layouts.master')

@section('title', __('Ride Details'))

@section('css')
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
    .map-placeholder {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        height: 200px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 500;
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
                            <span class="text-muted">Requested: {{ $ride->requested_at->format('M d, Y h:i A') }}</span>
                        </div>
                        <span class="ride-status status-{{ $ride->status }}">
                            {{ ucfirst(str_replace('_', ' ', $ride->status)) }}
                        </span>
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
                                    <p class="info-value mb-1">{{ $ride->pickup_address ?? 'Lat: ' . $ride->pickup_latitude . ', Lng: ' . $ride->pickup_longitude }}</p>
                                    <small class="text-muted">Coordinates: {{ $ride->pickup_latitude }}, {{ $ride->pickup_longitude }}</small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                @if($ride->dropoff_latitude)
                                <div class="info-card p-3 bg-light">
                                    <h6 class="info-label">Dropoff Location</h6>
                                    <p class="info-value mb-1">{{ $ride->dropoff_address ?? 'Lat: ' . $ride->dropoff_latitude . ', Lng: ' . $ride->dropoff_longitude }}</p>
                                    <small class="text-muted">Coordinates: {{ $ride->dropoff_latitude }}, {{ $ride->dropoff_longitude }}</small>
                                </div>
                                @endif
                            </div>
                        </div>

                        <div class="map-placeholder mb-4">
                            <i class="bx bx-map bx-lg me-2"></i>
                            <span>Route Map Display</span>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center p-2">
                                    <h6 class="info-label">Distance</h6>
                                    <p class="info-value">{{ $ride->distance_km ?? '0.00' }} km</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-2">
                                    <h6 class="info-label">Duration</h6>
                                    <p class="info-value">{{ $ride->duration_minutes ?? '0' }} minutes</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-2">
                                    <h6 class="info-label">Vehicle Type</h6>
                                    <p class="info-value">{{ $ride->vehicleType->name ?? 'N/A' }}</p>
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
                                        <td>${{ number_format($offer->proposed_price, 2) }}</td>
                                        <td>{{ $offer->eta_minutes }} minutes</td>
                                        <td>
                                            <span class="badge bg-{{ $offer->status == 'accepted' ? 'success' : ($offer->status == 'rejected' ? 'danger' : ($offer->status == 'expired' ? 'warning' : 'info')) }}">
                                                {{ ucfirst($offer->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $offer->offered_at->format('M d, h:i A') }}</td>
                                        <td>{{ $offer->note ?? '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
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
                        <!-- Add more passenger details if available -->
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
                        <!-- Add more driver details if available -->
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
                            <span class="info-value">${{ number_format($ride->vehicleType->base_fare, 2) }}</span>
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
                        <div class="d-flex justify-content-between mb-2">
                            <span class="info-label">Subtotal:</span>
                            <span class="info-value">${{ number_format($ride->subtotal, 2) }}</span>
                        </div>

                        @if($ride->discount_amount > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="info-label">Discount:</span>
                            <span class="info-value text-danger">-${{ number_format($ride->discount_amount, 2) }}</span>
                        </div>
                        @endif

                        @if($ride->extra_charges > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="info-label">Extra Charges:</span>
                            <span class="info-value text-success">+${{ number_format($ride->extra_charges, 2) }}</span>
                        </div>
                        @endif

                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="info-label fw-bold">Total Fare:</span>
                            <span class="info-value fw-bold fs-5">${{ number_format($ride->total_fare, 2) }}</span>
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
                                    <small class="text-muted">{{ $ride->requested_at->format('M d, Y h:i A') }}</small>
                                </div>
                            </div>

                            @if($ride->accepted_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Accepted</h6>
                                    <small class="text-muted">{{ $ride->accepted_at->format('M d, Y h:i A') }}</small>
                                </div>
                            </div>
                            @endif

                            @if($ride->started_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-info"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Started</h6>
                                    <small class="text-muted">{{ $ride->started_at->format('M d, Y h:i A') }}</small>
                                </div>
                            </div>
                            @endif

                            @if($ride->completed_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Completed</h6>
                                    <small class="text-muted">{{ $ride->completed_at->format('M d, Y h:i A') }}</small>
                                </div>
                            </div>
                            @endif

                            @if($ride->cancelled_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-danger"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Cancelled</h6>
                                    <small class="text-muted">{{ $ride->cancelled_at->format('M d, Y h:i A') }}</small>
                                    @if($ride->cancel_reason)
                                    <p class="mb-0"><small>Reason: {{ $ride->cancel_reason }}</small></p>
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
<script>
    $(document).ready(function() {
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
    });
</script>
@endsection
