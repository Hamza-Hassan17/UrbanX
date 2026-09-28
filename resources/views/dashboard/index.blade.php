@extends('layouts.master')

@section('title', 'Dashboard')

@section('css')
<style>
    .stats-card {
        transition: all 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    }

    canvas {
        max-height: 300px;
    }
</style>
@endsection

@section('content')
<div class="row g-4">
    <!-- Welcome Section -->
    <div class="col-xl-4 col-lg-6">
        <div class="card">
            <div class="d-flex align-items-end row">
                <div class="col-7">
                    <div class="card-body">
                        <h5 class="card-title mb-0">Hi {{ Auth::user()->name }}! 🎉</h5>
                        <p class="mb-2">Here’s what’s happening today:</p>
                        <a href="{{ route('profile.index') }}" class="btn btn-primary">View Profile</a>
                    </div>
                </div>
                <div class="col-5 text-center">
                    <img src="{{ asset('assets/img/illustrations/card-advance-sale.png') }}" height="120" alt="Profile">
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="col-xl-8 col-lg-6">
        <div class="row g-4">
            <div class="col-sm-4">
                <div class="card stats-card border-0 bg-light">
                    <div class="card-body">
                        <h6>Total Rides</h6>
                        <h3 class="fw-bold">1,248</h3>
                        <small class="text-success fw-semibold">+12% from last week</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card stats-card border-0 bg-light">
                    <div class="card-body">
                        <h6>Total Earnings</h6>
                        <h3 class="fw-bold">$7,540</h3>
                        <small class="text-primary fw-semibold">+8% from last week</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card stats-card border-0 bg-light">
                    <div class="card-body">
                        <h6>Avg. Rating</h6>
                        <h3 class="fw-bold">4.87 ⭐</h3>
                        <small class="text-warning fw-semibold">+0.2 improvement</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@canany(['view driver', 'view support requests'])
    @php
        $dashboardPendingVerifications = auth()->user()->can('view driver')
            ? \App\Models\DriverVerification::where('status', 'submitted')->count()
            : 0;
        $dashboardPendingSupportRequests = auth()->user()->can('view support requests')
            ? \App\Models\SupportRequest::where('status', 'pending')->count()
            : 0;
    @endphp
    @if ($dashboardPendingVerifications > 0 || $dashboardPendingSupportRequests > 0)
        <div class="row g-4 mt-1">
            @if ($dashboardPendingVerifications > 0)
                <div class="col-md-6">
                    <a href="{{ route('dashboard.drivers.pending-verifications') }}" class="text-decoration-none">
                        <div class="card border-0 bg-label-warning">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">{{ __('Pending Driver Verifications') }}</h6>
                                    <small>{{ __('Awaiting KYC document review') }}</small>
                                </div>
                                <h3 class="fw-bold mb-0">{{ $dashboardPendingVerifications }}</h3>
                            </div>
                        </div>
                    </a>
                </div>
            @endif
            @if ($dashboardPendingSupportRequests > 0)
                <div class="col-md-6">
                    <a href="{{ route('dashboard.support-requests.index', ['status' => 'pending']) }}" class="text-decoration-none">
                        <div class="card border-0 bg-label-info">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">{{ __('Pending Support Requests') }}</h6>
                                    <small>{{ __('Drivers awaiting a response') }}</small>
                                </div>
                                <h3 class="fw-bold mb-0">{{ $dashboardPendingSupportRequests }}</h3>
                            </div>
                        </div>
                    </a>
                </div>
            @endif
        </div>
    @endif
@endcanany

<!-- Charts Row -->
<div class="row g-4 mt-1">
    <!-- Ride Requests Chart -->
    <div class="col-xl-8 col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Ride Requests Trend</h5>
                <small class="text-muted">Last 7 Days</small>
            </div>
            <div class="card-body">
                <canvas id="rideRequestsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Active Drivers Chart -->
    <div class="col-xl-4 col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Active Drivers</h5>
                <small class="text-muted">Today</small>
            </div>
            <div class="card-body">
                <canvas id="driverActivityChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Revenue Breakdown -->
<div class="row g-4 mt-1">
    <div class="col-xl-6 col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Revenue Breakdown</h5>
                <small class="text-muted">By Category</small>
            </div>
            <div class="card-body">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-xl-6 col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Top Cities</h5>
                <small class="text-muted">Based on Rides</small>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Karachi</span><span>520 rides</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Lahore</span><span>370 rides</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Islamabad</span><span>260 rides</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Hyderabad</span><span>180 rides</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Ride Requests Trend
    const rideRequestsCtx = document.getElementById('rideRequestsChart').getContext('2d');
    new Chart(rideRequestsCtx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Ride Requests',
                data: [120, 150, 180, 220, 200, 240, 260],
                fill: true,
                backgroundColor: 'rgba(59,130,246,0.1)',
                borderColor: '#3b82f6',
                tension: 0.4,
                pointRadius: 4
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Active Drivers (Pie Chart)
    const driverActivityCtx = document.getElementById('driverActivityChart').getContext('2d');
    new Chart(driverActivityCtx, {
        type: 'doughnut',
        data: {
            labels: ['Online', 'Offline', 'On Trip'],
            datasets: [{
                data: [65, 25, 10],
                backgroundColor: ['#22c55e', '#e5e7eb', '#3b82f6']
            }]
        },
        options: {
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Revenue Breakdown
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: ['Economy', 'Business', 'Luxury', 'Delivery'],
            datasets: [{
                label: 'Revenue ($)',
                data: [2500, 3200, 1800, 1040],
                backgroundColor: ['#60a5fa', '#34d399', '#f59e0b', '#ef4444']
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
</script>
@endsection
