{{--
    Delivery workspace dashboard (Phase 3 of the admin workspace split).
    Real KPIs from HomeController::deliveryKpis(). Food vs Parcel mirrors
    DeliveryController's queue logic. revenue_today is null (not just
    hidden) for anyone without 'export payroll'.
--}}
<div class="row g-4">
    <div class="col-sm-4">
        <div class="card stats-card border-0 bg-light">
            <div class="card-body">
                <h6>{{ __('Orders Today') }}</h6>
                <h3 class="fw-bold">{{ $deliveryKpis['orders_today'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stats-card border-0 bg-light">
            <div class="card-body">
                <h6>{{ __('Food Orders') }}</h6>
                <h3 class="fw-bold">{{ $deliveryKpis['food_today'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stats-card border-0 bg-light">
            <div class="card-body">
                <h6>{{ __('Parcel Jobs') }}</h6>
                <h3 class="fw-bold">{{ $deliveryKpis['parcel_today'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stats-card border-0 bg-light">
            <div class="card-body">
                <h6>{{ __('Completed Today') }}</h6>
                <h3 class="fw-bold">{{ $deliveryKpis['completed_today'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stats-card border-0 bg-light">
            <div class="card-body">
                <h6>{{ __('Riders Available') }}</h6>
                <h3 class="fw-bold text-success">{{ $deliveryKpis['riders_available'] }}</h3>
            </div>
        </div>
    </div>
    @if ($deliveryKpis['revenue_today'] !== null)
        <div class="col-sm-4">
            <div class="card stats-card border-0 bg-light">
                <div class="card-body">
                    <h6>{{ __('Revenue Today') }}</h6>
                    <h3 class="fw-bold">{{ \App\Helpers\Helper::formatCurrency($deliveryKpis['revenue_today']) }}</h3>
                </div>
            </div>
        </div>
    @endif
</div>

<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>{{ __('Orders Trend') }}</h5>
                <small class="text-muted">{{ __('Last 7 Days') }}</small>
            </div>
            <div class="card-body">
                <canvas id="deliveryTrendChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    new Chart(document.getElementById('deliveryTrendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: @json($deliveryKpis['trend']->pluck('label')),
            datasets: [{
                label: 'Orders',
                data: @json($deliveryKpis['trend']->pluck('count')),
                fill: true,
                backgroundColor: 'rgba(234,88,12,0.1)',
                borderColor: '#ea580c',
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
