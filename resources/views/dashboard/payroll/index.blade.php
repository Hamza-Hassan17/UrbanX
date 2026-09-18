@extends('layouts.master')

@section('title', __('Payroll'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Payroll') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">

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
                        <select name="driver_ids[]" class="form-control" multiple style="height: 42px;">
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" {{ in_array($driver->id, request('driver_ids', [])) ? 'selected' : '' }}>
                                    {{ $driver->name }} @if($driver->is_active !== 'active') ({{ __('inactive') }}) @endif
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
                                <th>{{ __('Total Earnings') }}</th>
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
                                    <td>{{ \App\Helpers\Helper::formatCurrency($row['total_earnings']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">{{ __('No earnings found for this selection.') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">{{ __('Grand Total') }}</th>
                                <th>{{ $summary['grand_total_rides'] }}</th>
                                <th>{{ \App\Helpers\Helper::formatCurrency($summary['grand_total']) }}</th>
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
