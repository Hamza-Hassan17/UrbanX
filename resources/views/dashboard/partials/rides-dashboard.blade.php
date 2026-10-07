{{--
    Rides workspace dashboard (Phase 3 of the admin workspace split).
    Real KPIs from HomeController::ridesKpis() -- taxi only (ride_type='ride'),
    matching the Rides queue's scope from Phase 2. revenue_today is null
    (not just hidden) for anyone without 'export payroll', so Operator
    genuinely never receives the number, not just a CSS-hidden one.
--}}
<div class="row g-4">
    <div class="col-sm-4">
        <div class="card stats-card border-0 bg-light">
            <div class="card-body">
                <h6>{{ __('Rides Today') }}</h6>
                <h3 class="fw-bold">{{ $ridesKpis['rides_today'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stats-card border-0 bg-light">
            <div class="card-body">
                <h6>{{ __('Completed Today') }}</h6>
                <h3 class="fw-bold">{{ $ridesKpis['completed_today'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stats-card border-0 bg-light">
            <div class="card-body">
                <h6>{{ __('Cancelled Today') }}</h6>
                <h3 class="fw-bold">{{ $ridesKpis['cancelled_today'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stats-card border-0 bg-light">
            <div class="card-body">
                <h6>{{ __('Drivers Available') }}</h6>
                <h3 class="fw-bold text-success">{{ $ridesKpis['drivers_available'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stats-card border-0 bg-light">
            <div class="card-body">
                <h6>{{ __('Drivers Busy') }}</h6>
                <h3 class="fw-bold">{{ $ridesKpis['drivers_busy'] }}</h3>
            </div>
        </div>
    </div>
    @if ($ridesKpis['revenue_today'] !== null)
        <div class="col-sm-4">
            <div class="card stats-card border-0 bg-light">
                <div class="card-body">
                    <h6>{{ __('Revenue Today') }}</h6>
                    <h3 class="fw-bold">{{ \App\Helpers\Helper::formatCurrency($ridesKpis['revenue_today']) }}</h3>
                </div>
            </div>
        </div>
    @endif
</div>

<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>{{ __('Ride Requests Trend') }}</h5>
                <small class="text-muted">{{ __('Last 7 Days') }}</small>
            </div>
            <div class="card-body">
                <canvas id="ridesTrendChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Inline, not @push/@stack -- this layout has no @stack('scripts'), only
     a @yield('script') inside layouts/script.blade.php, which a nested
     @include can't safely target. Matches how custom-rides/index.blade.php
     already embeds its charts/JS directly in @section('content'). --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    new Chart(document.getElementById('ridesTrendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: @json($ridesKpis['trend']->pluck('label')),
            datasets: [{
                label: 'Rides',
                data: @json($ridesKpis['trend']->pluck('count')),
                fill: true,
                backgroundColor: 'rgba(59,130,246,0.1)',
                borderColor: '#3b82f6',
                tension: 0.4,
                pointRadius: 4
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
</script>
