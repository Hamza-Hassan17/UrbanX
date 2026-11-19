@extends('layouts.master')

@section('title', __('Customer Profile'))

@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.user.index') }}">{{ __('Customers') }}</a></li>
    <li class="breadcrumb-item active">{{ $customer->name }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">{{ $customer->name }}</h4>
                    <p class="mb-0 text-muted">{{ $customer->email }} &middot; {{ $customer->phone }}</p>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                    <span class="badge bg-label-{{ $customer->is_active === 'active' ? 'success' : 'secondary' }}">
                        {{ ucfirst($customer->is_active) }}
                    </span>
                    @if ($customer->hasAcceptedCurrentTerms() && $customer->terms_accepted_at)
                        <span class="badge bg-label-success">{{ __('Terms Accepted') }} &middot; {{ \Carbon\Carbon::parse($customer->terms_accepted_at)->format('d M Y') }}</span>
                    @else
                        <span class="badge bg-label-danger">{{ __('Terms Not Accepted') }}</span>
                    @endif
                </div>
            </div>
        </div>

        @php
            $s = $insights['summary'];
            $groupColors = ['top' => 'success', 'at_risk' => 'danger', 'new' => 'info', 'regular' => 'primary', 'inactive' => 'secondary', 'never' => 'secondary'];
            $group = $insights['group'];
        @endphp
        {{-- History summary: every service, one place --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">{{ __('Customer history') }}</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-label-{{ $groupColors[$group] }} fs-6">{{ __(\App\Services\CustomerInsightsService::GROUPS[$group]) }}</span>
                    @if($insights['days_since_last'] !== null)
                        <small class="text-muted">{{ __('Last order') }}: {{ $insights['days_since_last'] === 0 ? __('today') : trans_choice(':count day ago|:count days ago', $insights['days_since_last'], ['count' => $insights['days_since_last']]) }}</small>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3"><div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">{{ __('Completed orders') }}</small>
                        <h4 class="mb-0">{{ $s['orders'] }}</h4>
                        <small class="text-muted">{{ __('Average customer') }}: {{ $insights['average_orders'] }}</small>
                    </div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">{{ __('Total spent') }}</small>
                        <h4 class="mb-0">{{ \App\Helpers\Helper::formatCurrency($s['spent']) }}</h4>
                        <small class="text-muted">{{ __('Average order') }}: {{ \App\Helpers\Helper::formatCurrency($s['avg_order']) }}</small>
                    </div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">{{ __('Requested / cancelled') }}</small>
                        <h4 class="mb-0">{{ $s['requested'] }} / {{ $s['cancelled'] }}</h4>
                        <small class="text-muted">{{ $s['requested'] > 0 ? round($s['cancelled'] / $s['requested'] * 100) : 0 }}% {{ __('cancelled') }}</small>
                    </div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">{{ __('Customer since') }}</small>
                        <h6 class="mb-0">{{ $s['first_at'] ? $s['first_at']->format('d M Y') : '--' }}</h6>
                        <small class="text-muted">{{ __('Last order') }}: {{ $s['last_at'] ? $s['last_at']->format('d M Y') : '--' }}</small>
                    </div></div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm mb-0">
                        <thead><tr>
                            <th>{{ __('Service') }}</th><th class="text-end">{{ __('Requested') }}</th><th class="text-end">{{ __('Completed') }}</th>
                            <th class="text-end">{{ __('Cancelled') }}</th><th class="text-end">{{ __('Spent') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach(\App\Services\CustomerInsightsService::SERVICES as $key => $label)
                            @php $row = $s['services'][$key]; @endphp
                            <tr>
                                <td>{{ __($label) }}</td>
                                <td class="text-end">{{ $row['requested'] }}</td>
                                <td class="text-end">{{ $row['completed'] }}</td>
                                <td class="text-end">{{ $row['cancelled'] }}</td>
                                <td class="text-end">{{ \App\Helpers\Helper::formatCurrency($row['spent']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row g-3">
                    <div class="col-md-5">
                        <h6 class="mb-1">{{ __('Spending by service') }}</h6>
                        <div id="customer-spend-pie"></div>
                    </div>
                    <div class="col-md-7">
                        <h6 class="mb-1">{{ __('Orders per month (last 12 months)') }}</h6>
                        <div id="customer-monthly-bar"></div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-rides" type="button">
                    {{ __('Rides') }} <span class="badge bg-label-primary">{{ $rides->count() }}</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-rentals" type="button">
                    {{ __('Rentals') }} <span class="badge bg-label-primary">{{ $rentals->count() }}</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-delivery" type="button">
                    {{ __('Food & Parcel Orders') }} <span class="badge bg-label-primary">{{ $deliveryRides->count() }}</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-wallet" type="button">
                    {{ __('Wallet') }} <span class="badge bg-label-primary">{{ $transactions->count() }}</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-complaints" type="button">
                    {{ __('Complaints') }} <span class="badge bg-label-primary">{{ $complaints->count() }}</span>
                </button>
            </li>
        </ul>

        <div class="tab-content">
            {{-- Rides --}}
            <div class="tab-pane fade show active" id="tab-rides">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table border-top">
                            <thead>
                                <tr>
                                    <th>{{ __('Ride') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Fare') }}</th>
                                    <th>{{ __('Requested') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rides as $ride)
                                    <tr>
                                        <td>RIDE-{{ $ride->id }}</td>
                                        <td><span class="badge bg-label-secondary">{{ ucfirst($ride->status) }}</span></td>
                                        <td>{{ \App\Helpers\Helper::formatCurrency($ride->total_fare) }}</td>
                                        <td>{{ optional($ride->requested_at)->format('d M Y h:i A') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">{{ __('No rides yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Rentals --}}
            <div class="tab-pane fade" id="tab-rentals">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table border-top">
                            <thead>
                                <tr>
                                    <th>{{ __('Booking') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Total') }}</th>
                                    <th>{{ __('Start') }}</th>
                                    <th>{{ __('End') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rentals as $rental)
                                    <tr>
                                        <td>{{ $rental->booking_id }}</td>
                                        <td><span class="badge bg-label-secondary">{{ ucfirst($rental->status) }}</span></td>
                                        <td>{{ \App\Helpers\Helper::formatCurrency($rental->total_amount) }}</td>
                                        <td>{{ optional($rental->start_time)->format('d M Y h:i A') }}</td>
                                        <td>{{ optional($rental->end_time)->format('d M Y h:i A') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">{{ __('No rentals yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Food & Parcel Orders --}}
            <div class="tab-pane fade" id="tab-delivery">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table border-top">
                            <thead>
                                <tr>
                                    <th>{{ __('Order') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Fare') }}</th>
                                    <th>{{ __('Requested') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($deliveryRides as $ride)
                                    <tr>
                                        <td>ORDER-{{ $ride->id }}</td>
                                        <td><span class="badge bg-label-{{ $ride->is_food ? 'warning' : 'info' }}">{{ $ride->is_food ? __('Food') : __('Parcel') }}</span></td>
                                        <td><span class="badge bg-label-secondary">{{ ucfirst($ride->status) }}</span></td>
                                        <td>{{ \App\Helpers\Helper::formatCurrency($ride->total_fare) }}</td>
                                        <td>{{ optional($ride->requested_at)->format('d M Y h:i A') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">{{ __('No food or parcel orders yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Wallet --}}
            <div class="tab-pane fade" id="tab-wallet">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table border-top">
                            <thead>
                                <tr>
                                    <th>{{ __('Transaction') }}</th>
                                    <th>{{ __('Service') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Method') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($transactions as $transaction)
                                    <tr>
                                        <td>{{ $transaction->trx_id }}</td>
                                        <td>{{ $transaction->service ? ucfirst($transaction->service) : '—' }}</td>
                                        <td>{{ \App\Helpers\Helper::formatCurrency($transaction->amount) }}</td>
                                        <td>{{ ucfirst($transaction->payment_method) }}</td>
                                        <td><span class="badge bg-label-secondary">{{ ucfirst($transaction->payment_status) }}</span></td>
                                        <td>{{ $transaction->created_at->format('d M Y h:i A') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">{{ __('No wallet transactions yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Complaints --}}
            <div class="tab-pane fade" id="tab-complaints">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table border-top">
                            <thead>
                                <tr>
                                    <th>{{ __('Subject') }}</th>
                                    <th>{{ __('Service') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Submitted') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($complaints as $complaint)
                                    <tr>
                                        <td>{{ $complaint->subject }}</td>
                                        <td>{{ $complaint->service ? ucfirst($complaint->service) : '—' }}</td>
                                        <td><span class="badge bg-label-secondary">{{ ucfirst($complaint->status) }}</span></td>
                                        <td>{{ $complaint->created_at->format('d M Y h:i A') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">{{ __('No complaints yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    @php
        $svcLabels = array_values(\App\Services\CustomerInsightsService::SERVICES);
        $spend = array_map(fn ($k) => round($insights['summary']['services'][$k]['spent']), array_keys(\App\Services\CustomerInsightsService::SERVICES));
        $monthKeys = array_keys($insights['monthly']);
        $monthLabels = array_map(fn ($ym) => \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('M y'), $monthKeys);
        $series = [];
        foreach (\App\Services\CustomerInsightsService::SERVICES as $k => $label) {
            $series[] = ['name' => $label, 'data' => array_map(fn ($ym) => $insights['monthly'][$ym][$k], $monthKeys)];
        }
    @endphp
    <script>
        (function () {
            if (typeof ApexCharts === 'undefined') return;
            const colors = ['#7367f0', '#ff9f43', '#28c76f', '#00cfe8'];
            const spend = @json($spend);
            const pieEl = document.querySelector('#customer-spend-pie');
            if (spend.some(v => v > 0)) {
                new ApexCharts(pieEl, {
                    chart: { type: 'donut', height: 260 },
                    labels: @json($svcLabels),
                    series: spend,
                    colors,
                    legend: { position: 'bottom' },
                    dataLabels: { enabled: true },
                    tooltip: { y: { formatter: v => 'Rs ' + v.toLocaleString() } },
                }).render();
            } else {
                pieEl.innerHTML = '<p class="text-muted small mt-3">No completed orders yet.</p>';
            }
            new ApexCharts(document.querySelector('#customer-monthly-bar'), {
                chart: { type: 'bar', height: 260, stacked: true, toolbar: { show: false } },
                series: @json($series),
                xaxis: { categories: @json($monthLabels) },
                yaxis: { labels: { formatter: v => Math.round(v) } },
                colors,
                legend: { position: 'bottom' },
                dataLabels: { enabled: false },
                plotOptions: { bar: { columnWidth: '55%' } },
            }).render();
        })();
    </script>
@endsection
