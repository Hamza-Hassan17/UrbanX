{{--
    Platform workspace dashboard -- combined overview, real KPIs from
    HomeController::platformKpis() (follow-up instruction superseding
    Phase 3's original "leave Platform's placeholder content alone").
--}}
<div class="row g-4">
    <div class="col-xl-4 col-lg-6">
        <div class="card">
            <div class="d-flex align-items-end row">
                <div class="col-7">
                    <div class="card-body">
                        <h5 class="card-title mb-0">Hi {{ Auth::user()->name }}! 🎉</h5>
                        <p class="mb-2">{{ __("Here's what's happening today:") }}</p>
                        <a href="{{ route('profile.index') }}" class="btn btn-primary">View Profile</a>
                    </div>
                </div>
                <div class="col-5 text-center">
                    <img src="{{ asset('assets/img/illustrations/card-advance-sale.png') }}" height="120" alt="Profile">
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8 col-lg-6">
        <div class="row g-4">
            <div class="col-sm-3">
                <div class="card stats-card border-0 bg-light">
                    <div class="card-body">
                        <h6>{{ __('Rides Today') }}</h6>
                        <h3 class="fw-bold">{{ $platformKpis['rides_today'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="card stats-card border-0 bg-light">
                    <div class="card-body">
                        <h6>{{ __('Orders Today') }}</h6>
                        <h3 class="fw-bold">{{ $platformKpis['orders_today'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="card stats-card border-0 bg-light">
                    <div class="card-body">
                        <h6>{{ __('Completed Today') }}</h6>
                        <h3 class="fw-bold">{{ $platformKpis['completed_today'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="card stats-card border-0 bg-light">
                    <div class="card-body">
                        <h6>{{ __('Cancelled Today') }}</h6>
                        <h3 class="fw-bold">{{ $platformKpis['cancelled_today'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-sm-4">
        <div class="card stats-card border-0 bg-light">
            <div class="card-body">
                <h6>{{ __('Drivers Available') }}</h6>
                <h3 class="fw-bold text-success">{{ $platformKpis['drivers_available'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stats-card border-0 bg-light">
            <div class="card-body">
                <h6>{{ __('Riders Available') }}</h6>
                <h3 class="fw-bold text-success">{{ $platformKpis['riders_available'] }}</h3>
            </div>
        </div>
    </div>
    @if ($platformKpis['revenue_today'] !== null)
        <div class="col-sm-4">
            <div class="card stats-card border-0 bg-light">
                <div class="card-body">
                    <h6>{{ __('Revenue Today') }}</h6>
                    <h3 class="fw-bold">{{ \App\Helpers\Helper::formatCurrency($platformKpis['revenue_today']) }}</h3>
                </div>
            </div>
        </div>
    @endif
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

<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>{{ __('Rides & Orders Trend') }}</h5>
                <small class="text-muted">{{ __('Last 7 Days') }}</small>
            </div>
            <div class="card-body">
                <canvas id="platformTrendChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    new Chart(document.getElementById('platformTrendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: @json($platformKpis['labels']),
            datasets: [
                {
                    label: 'Rides',
                    data: @json($platformKpis['rides_trend']),
                    fill: true,
                    backgroundColor: 'rgba(59,130,246,0.1)',
                    borderColor: '#3b82f6',
                    tension: 0.4,
                    pointRadius: 4
                },
                {
                    label: 'Orders',
                    data: @json($platformKpis['orders_trend']),
                    fill: true,
                    backgroundColor: 'rgba(234,88,12,0.1)',
                    borderColor: '#ea580c',
                    tension: 0.4,
                    pointRadius: 4
                }
            ]
        },
        options: {
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: true } }
        }
    });
</script>
