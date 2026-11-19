@extends('layouts.master')

@section('title', __('Customer Insights'))

@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.user.index') }}">{{ __('Customers') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Customer Insights') }}</li>
@endsection

@section('content')
@php
    $groups = \App\Services\CustomerInsightsService::GROUPS;
    $colors = ['top' => 'success', 'at_risk' => 'danger', 'new' => 'info', 'regular' => 'primary', 'inactive' => 'secondary', 'never' => 'dark'];
    $help = [
        'top' => __('At least the average number of orders (and 3+), ordered in the last :d days', ['d' => $atRiskDays]),
        'at_risk' => __('Used to order often, but nothing for :d+ days. Win them back.', ['d' => $atRiskDays]),
        'new' => __('First order in the last 30 days'),
        'regular' => __('Ordered recently, below average so far'),
        'inactive' => __('No order for 60+ days'),
        'never' => __('Signed up, never ordered'),
    ];
@endphp
<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-0">{{ __('Customer Insights') }}</h4>
            <small class="text-muted">{{ $totalCustomers }} {{ __('customers') }} &middot; {{ __('average') }} {{ $average }} {{ __('completed orders per ordering customer') }}</small>
        </div>
        <form method="POST" action="{{ route('dashboard.customer-insights.settings') }}" class="d-flex align-items-center gap-2">
            @csrf
            <label class="small text-nowrap mb-0" for="customer_at_risk_days">{{ __('"At risk" after') }}</label>
            <input type="number" min="1" max="365" class="form-control form-control-sm" style="width:80px" id="customer_at_risk_days" name="customer_at_risk_days" value="{{ $atRiskDays }}">
            <span class="small">{{ __('days') }}</span>
            <button class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>
        </form>
    </div>

    {{-- Group cards (click to filter the list below) --}}
    <div class="row g-3 mb-4">
        @foreach($groups as $key => $label)
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('dashboard.customer-insights.index', ['group' => $key]) }}" class="text-decoration-none">
                    <div class="card h-100 {{ $group === $key ? 'border border-2 border-' . $colors[$key] : '' }}">
                        <div class="card-body p-3">
                            <span class="badge bg-label-{{ $colors[$key] }} mb-2">{{ __($label) }}</span>
                            <h3 class="mb-1">{{ $groupCounts[$key] }}</h3>
                            <small class="text-muted d-block" style="line-height:1.2">{{ $help[$key] }}</small>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    {{-- Charts --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card h-100"><div class="card-body">
                <h6>{{ __('Customers by group') }}</h6>
                <div id="chart-groups"></div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100"><div class="card-body">
                <h6>{{ __('Active customers per month') }}</h6>
                <div id="chart-active"></div>
            </div></div>
        </div>
        <div class="col-lg-3">
            <div class="card h-100"><div class="card-body">
                <h6>{{ __('Completed orders by service') }}</h6>
                <div id="chart-services"></div>
            </div></div>
        </div>
    </div>

    {{-- Customers in the selected group --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">
                <span class="badge bg-label-{{ $colors[$group] }}">{{ __($groups[$group]) }}</span>
                <small class="text-muted ms-1">{{ $list->count() }} {{ __('shown') }}</small>
            </h5>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="group" value="{{ $group }}">
                <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('Search name, phone, email') }}">
                <button class="btn btn-sm btn-primary">{{ __('Search') }}</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Customer') }}</th>
                        <th class="text-end">{{ __('Orders') }}</th>
                        <th class="text-end">{{ __('Spent') }}</th>
                        <th>{{ __('Uses') }}</th>
                        <th>{{ __('Last order') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($list as $r)
                    <tr>
                        <td>
                            <a href="{{ route('dashboard.customers.show', $r['id']) }}" class="fw-semibold">{{ $r['name'] ?: '#' . $r['id'] }}</a>
                            <small class="d-block text-muted">{{ $r['phone'] }}</small>
                        </td>
                        <td class="text-end">{{ $r['orders'] }}</td>
                        <td class="text-end">{{ \App\Helpers\Helper::formatCurrency($r['spent']) }}</td>
                        <td>
                            @if($r['services'])
                                @foreach(['ride' => '🚕', 'parcel' => '📦', 'food' => '🍔', 'rental' => '🚘'] as $k => $icon)
                                    @if($r['services'][$k]['completed'] > 0)
                                        <span class="badge bg-label-secondary" title="{{ \App\Services\CustomerInsightsService::SERVICES[$k] }}">{{ $icon }} {{ $r['services'][$k]['completed'] }}</span>
                                    @endif
                                @endforeach
                            @endif
                        </td>
                        <td>
                            @if($r['last_at'])
                                {{ $r['last_at']->format('d M Y') }}
                                <small class="d-block {{ $r['days_since'] >= $atRiskDays ? 'text-danger' : 'text-muted' }}">{{ $r['days_since'] }} {{ __('days ago') }}</small>
                            @else
                                <span class="text-muted">--</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('dashboard.customers.show', $r['id']) }}" class="btn btn-sm btn-outline-primary">{{ __('History') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No customers in this group.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    (function () {
        if (typeof ApexCharts === 'undefined') return;
        const groupLabels = @json(array_values(array_map('__', $groups)));
        const groupCounts = @json(array_values($groupCounts));
        new ApexCharts(document.querySelector('#chart-groups'), {
            chart: { type: 'donut', height: 280 },
            labels: groupLabels,
            series: groupCounts,
            colors: ['#28c76f', '#ea5455', '#00cfe8', '#7367f0', '#a8aaae', '#4b4b4b'],
            legend: { position: 'bottom' },
        }).render();

        const active = @json($activeByMonth);
        new ApexCharts(document.querySelector('#chart-active'), {
            chart: { type: 'bar', height: 280, toolbar: { show: false } },
            series: [{ name: 'Active customers', data: Object.values(active) }],
            xaxis: { categories: Object.keys(active) },
            yaxis: { labels: { formatter: v => Math.round(v) } },
            colors: ['#7367f0'],
            dataLabels: { enabled: false },
            plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
        }).render();

        const services = @json($serviceTotals);
        new ApexCharts(document.querySelector('#chart-services'), {
            chart: { type: 'pie', height: 280 },
            labels: Object.keys(services),
            series: Object.values(services),
            colors: ['#7367f0', '#ff9f43', '#28c76f', '#00cfe8'],
            legend: { position: 'bottom' },
        }).render();
    })();
</script>
@endsection
