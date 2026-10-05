@extends('layouts.master')

@section('title', __('Payroll'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Payroll') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        @include('dashboard.finance.partials.tabs')

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Driver Earnings') }}</h5>
                <small class="text-muted">{{ __('Select a date range and driver scope to calculate earnings, then export or email the report.') }}</small>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('dashboard.payroll.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Start Date') }}</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('End Date') }}</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Drivers') }}</label>
                        <select name="driver_ids[]" class="select2 form-select" multiple>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" {{ in_array($driver->id, request('driver_ids', [])) ? 'selected' : '' }}>
                                    {{ $driver->name }} @if($driver->phone)({{ $driver->phone }})@endif #{{ $driver->id }} @if($driver->is_active !== 'active') ({{ __('inactive') }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">{{ __('Leave empty to include all drivers (including inactive).') }}</small>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">{{ __('Calculate') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($summary)
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Results') }}</h5>
                    <div class="d-flex gap-2">
                        <form method="GET" action="{{ route('dashboard.payroll.export-pdf') }}">
                            @foreach (request()->except('_token') as $key => $value)
                                @if (is_array($value))
                                    @foreach ($value as $v)
                                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                                    @endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="ti ti-file-type-pdf"></i> {{ __('Export PDF') }}
                            </button>
                        </form>
                        <form method="GET" action="{{ route('dashboard.payroll.export-excel') }}">
                            @foreach (request()->except('_token') as $key => $value)
                                @if (is_array($value))
                                    @foreach ($value as $v)
                                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                                    @endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <button type="submit" class="btn btn-outline-success btn-sm">
                                <i class="ti ti-file-spreadsheet"></i> {{ __('Export Excel') }}
                            </button>
                        </form>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table border-top">
                        <thead>
                            <tr>
                                <th>{{ __('Driver') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Total Rides') }}</th>
                                <th>{{ __('Gross Fare') }}</th>
                                <th>{{ __('Commission') }}</th>
                                <th>{{ __('SST on Commission') }}</th>
                                <th>{{ __('SST on Ride Fare') }}</th>
                                <th>{{ __('Driver\'s Net Income') }}</th>
                                <th>{{ __('Rides') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($summary['rows'] as $row)
                                <tr>
                                    <td>{{ $row['driver_name'] }}</td>
                                    <td>
                                        <span class="badge {{ $row['is_active'] === 'active' ? 'bg-label-success' : 'bg-label-secondary' }}">
                                            {{ ucfirst($row['is_active']) }}
                                        </span>
                                    </td>
                                    <td>{{ $row['total_rides'] }}</td>
                                    <td>{{ \App\Helpers\Helper::formatCurrency($row['gross_fare']) }}</td>
                                    <td class="text-danger">({{ \App\Helpers\Helper::formatCurrency($row['commission']) }})</td>
                                    <td class="text-danger">({{ \App\Helpers\Helper::formatCurrency($row['sst_on_commission']) }})</td>
                                    <td class="text-danger">({{ \App\Helpers\Helper::formatCurrency($row['sst_on_ride_fare']) }})</td>
                                    <td><strong>{{ \App\Helpers\Helper::formatCurrency($row['total_earnings']) }}</strong></td>
                                    <td>
                                        <a href="{{ route('dashboard.finance.reports', ['start_date' => request('start_date'), 'end_date' => request('end_date'), 'driver_ids' => [$row['driver_id']], 'type' => 'all']) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="ti ti-eye"></i> {{ __('View Rides') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted">{{ __('No earnings found for this selection.') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">{{ __('Grand Total') }}</th>
                                <th>{{ $summary['grand_total_rides'] }}</th>
                                <th>{{ \App\Helpers\Helper::formatCurrency($summary['grand_total_gross']) }}</th>
                                <th>({{ \App\Helpers\Helper::formatCurrency($summary['grand_total_commission']) }})</th>
                                <th colspan="2">({{ \App\Helpers\Helper::formatCurrency($summary['grand_total_sst']) }})</th>
                                <th>{{ \App\Helpers\Helper::formatCurrency($summary['grand_total']) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Bulk-Send Weekly Reports') }}</h5>
                <small class="text-muted">{{ __('Emails every currently active driver their own individual earnings PDF for the selected date range.') }}</small>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('dashboard.payroll.bulk-send') }}" class="row g-3 align-items-end"
                    onsubmit="return confirm('{{ __('This will email every active driver their earnings report. Continue?') }}');">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Start Date') }}</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('End Date') }}</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-warning">
                            <i class="ti ti-mail"></i> {{ __('Send to All Active Drivers') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection
