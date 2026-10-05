@extends('layouts.master')

@section('title', __('Finance Reports'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Finance') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        @include('dashboard.finance.partials.tabs')

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Tax & Commission Report') }}</h5>
                <small class="text-muted">{{ __('Every completed ride in the range, with pickup, drop-off and the commission/SST breakdown. Leave drivers empty for all drivers.') }}</small>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('dashboard.finance.reports') }}" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">{{ __('Start Date') }}</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('End Date') }}</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Drivers') }}</label>
                        <select name="driver_ids[]" class="select2 form-select" multiple>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" {{ in_array($driver->id, request('driver_ids', [])) ? 'selected' : '' }}>
                                    {{ $driver->name }} @if($driver->phone)({{ $driver->phone }})@endif #{{ $driver->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Report') }}</label>
                        <select name="type" class="form-select">
                            @foreach (\App\Services\FinanceReportService::TYPES as $value => $option)
                                <option value="{{ $value }}" {{ request('type', 'all') === $value ? 'selected' : '' }}>{{ __($option['label']) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">{{ __('Generate') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($report)
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">{{ __('Results') }}</h5>
                        <small class="text-muted">{{ $report['start_date'] }} → {{ $report['end_date'] }} &middot; {{ __('Commission') }} {{ $report['commission_percent'] }}% &middot; {{ __('SST') }} {{ $report['sst_percent'] }}%</small>
                    </div>
                    <div class="d-flex gap-2">
                        @foreach (['dashboard.finance.reports.export-pdf' => ['btn-outline-danger', 'ti-file-type-pdf', 'Export PDF'], 'dashboard.finance.reports.export-excel' => ['btn-outline-success', 'ti-file-spreadsheet', 'Export Excel']] as $routeName => $style)
                            <form method="GET" action="{{ route($routeName) }}">
                                @foreach (request()->except('_token') as $key => $value)
                                    @if (is_array($value))
                                        @foreach ($value as $v)
                                            <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                                        @endforeach
                                    @else
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endif
                                @endforeach
                                <button type="submit" class="btn {{ $style[0] }} btn-sm">
                                    <i class="ti {{ $style[1] }}"></i> {{ __($style[2]) }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>

                @if ($report['type'] === 'all')
                <div class="table-responsive">
                    <table class="table table-sm border-top align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('Ride') }}</th>
                                <th>{{ __('Completed') }}</th>
                                <th>{{ __('Pickup') }}</th>
                                <th>{{ __('Drop-off') }}</th>
                                <th>{{ __('Distance / Time') }}</th>
                                <th>{{ __('Gross Fare') }}</th>
                                <th>{{ __('Commission') }}</th>
                                <th>{{ __('SST on Commission') }}</th>
                                <th>{{ __('SST on Ride Fare') }}</th>
                                <th>{{ __('Driver\'s Net Income') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['groups'] as $group)
                                <tr class="table-secondary">
                                    <td colspan="10">
                                        <strong>{{ $group['driver_name'] }}</strong> @if($group['driver_id'])#{{ $group['driver_id'] }}@endif
                                        @if($group['phone']) &middot; {{ $group['phone'] }} @endif
                                        &middot; {{ $group['total_rides'] }} {{ __('ride(s)') }}
                                    </td>
                                </tr>
                                @foreach ($group['rides'] as $ride)
                                    <tr>
                                        <td>#{{ $ride['ride_id'] }} <small class="text-muted">{{ $ride['ride_type'] }}</small></td>
                                        <td>{{ $ride['completed_at'] }}</td>
                                        <td style="max-width:220px">{{ $ride['pickup'] }}</td>
                                        <td style="max-width:220px">{{ $ride['dropoff'] }}</td>
                                        <td>{{ $ride['distance_km'] }} km / {{ $ride['duration_minutes'] }} min</td>
                                        <td>{{ \App\Helpers\Helper::formatCurrency($ride['gross_fare']) }}</td>
                                        <td class="text-danger">({{ \App\Helpers\Helper::formatCurrency($ride['commission']) }})</td>
                                        <td class="text-danger">({{ \App\Helpers\Helper::formatCurrency($ride['sst_on_commission']) }})</td>
                                        <td class="text-danger">({{ \App\Helpers\Helper::formatCurrency($ride['sst_on_ride_fare']) }})</td>
                                        <td><strong>{{ \App\Helpers\Helper::formatCurrency($ride['driver_income']) }}</strong></td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <th colspan="5" class="text-end">{{ __('Subtotal') }}: {{ $group['driver_name'] }}</th>
                                    <th>{{ \App\Helpers\Helper::formatCurrency($group['gross_fare']) }}</th>
                                    <th class="text-danger">({{ \App\Helpers\Helper::formatCurrency($group['commission']) }})</th>
                                    <th class="text-danger" colspan="2">({{ \App\Helpers\Helper::formatCurrency($group['sst']) }})</th>
                                    <th>{{ \App\Helpers\Helper::formatCurrency($group['net_income']) }}</th>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center text-muted">{{ __('No completed rides found for this selection.') }}</td></tr>
                            @endforelse
                        </tbody>
                        @if ($report['groups']->isNotEmpty())
                            <tfoot>
                                <tr>
                                    <th colspan="5">{{ __('Grand Total') }} ({{ $report['totals']['total_rides'] }} {{ __('rides') }})</th>
                                    <th>{{ \App\Helpers\Helper::formatCurrency($report['totals']['gross_fare']) }}</th>
                                    <th class="text-danger">({{ \App\Helpers\Helper::formatCurrency($report['totals']['commission']) }})</th>
                                    <th class="text-danger" colspan="2">({{ \App\Helpers\Helper::formatCurrency($report['totals']['sst']) }})</th>
                                    <th>{{ \App\Helpers\Helper::formatCurrency($report['totals']['net_income']) }}</th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm border-top align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('Ride') }}</th>
                                <th>{{ __('Completed') }}</th>
                                <th>{{ __('Pickup') }}</th>
                                <th>{{ __('Drop-off') }}</th>
                                <th>{{ __('Distance / Time') }}</th>
                                <th>{{ __('Gross Fare') }}</th>
                                <th>{{ $report['type_label'] }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['groups'] as $group)
                                <tr class="table-secondary">
                                    <td colspan="7">
                                        <strong>{{ $group['driver_name'] }}</strong> @if($group['driver_id'])#{{ $group['driver_id'] }}@endif
                                        &middot; {{ $group['total_rides'] }} {{ __('ride(s)') }}
                                    </td>
                                </tr>
                                @foreach ($group['rides'] as $ride)
                                    <tr>
                                        <td>#{{ $ride['ride_id'] }} <small class="text-muted">{{ $ride['ride_type'] }}</small></td>
                                        <td>{{ $ride['completed_at'] }}</td>
                                        <td style="max-width:220px">{{ $ride['pickup'] }}</td>
                                        <td style="max-width:220px">{{ $ride['dropoff'] }}</td>
                                        <td>{{ $ride['distance_km'] }} km / {{ $ride['duration_minutes'] }} min</td>
                                        <td>{{ \App\Helpers\Helper::formatCurrency($ride['gross_fare']) }}</td>
                                        <td class="text-danger"><strong>{{ \App\Helpers\Helper::formatCurrency($ride['selected_amount']) }}</strong></td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <th colspan="6" class="text-end">{{ __('Subtotal') }}: {{ $group['driver_name'] }}</th>
                                    <th class="text-danger">{{ \App\Helpers\Helper::formatCurrency($group['selected_total']) }}</th>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">{{ __('No completed rides found for this selection.') }}</td></tr>
                            @endforelse
                        </tbody>
                        @if ($report['groups']->isNotEmpty())
                            <tfoot>
                                <tr>
                                    <th colspan="6">{{ __('Grand Total') }} ({{ $report['totals']['total_rides'] }} {{ __('rides') }})</th>
                                    <th class="text-danger">{{ \App\Helpers\Helper::formatCurrency($report['totals']['selected_total']) }}</th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                @endif
            </div>
        @endif
    </div>
@endsection
