@extends('layouts.master')

@section('title', __('Reports'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Reports') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Operator / Admin Activity') }}</h5>
                <small class="text-muted">{{ __('Rides created and status changes made from the dashboard, per admin.') }}</small>
            </div>
            <div class="table-responsive">
                <table class="table border-top">
                    <thead>
                        <tr>
                            <th>{{ __('Admin') }}</th>
                            <th>{{ __('Rides Created') }}</th>
                            <th>{{ __('Status Changes Made') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($operatorStats as $operator)
                            <tr>
                                <td>{{ $operator->name }}</td>
                                <td>{{ $operator->rides_created }}</td>
                                <td>{{ $operator->status_changes_made }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted">{{ __('No admin activity recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Driver Job Counts') }}</h5>
                <small class="text-muted">{{ __('Total rides assigned, completed, and cancelled per driver.') }}</small>
            </div>
            <div class="table-responsive">
                <table class="table border-top">
                    <thead>
                        <tr>
                            <th>{{ __('Driver') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Total Assigned') }}</th>
                            <th>{{ __('Completed') }}</th>
                            <th>{{ __('Cancelled') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($driverStats as $driver)
                            <tr>
                                <td>{{ $driver->name }}</td>
                                <td>
                                    <span class="badge {{ $driver->driver_status === 'available' ? 'bg-label-success' : 'bg-label-secondary' }}">
                                        {{ ucfirst($driver->driver_status ?? 'unknown') }}
                                    </span>
                                </td>
                                <td>{{ $driver->total_assigned }}</td>
                                <td>{{ $driver->completed }}</td>
                                <td>{{ $driver->cancelled }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">{{ __('No driver activity recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
