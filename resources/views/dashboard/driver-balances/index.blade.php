@extends('layouts.master')

@section('title', __('Driver Balances'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Driver Balances') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Taxi Driver Outstanding Balance') }}</h5>
                <small class="text-muted">{{ __('Limit') }}: {{ \App\Helpers\Helper::formatCurrency($outstandingLimit) }} -- {{ __('commission + SST owed on cash fares; drivers at or above this are blocked from new offers until they settle.') }}</small>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Driver') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('City') }}</th>
                            <th>{{ __('Balance') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($drivers as $driver)
                            <tr>
                                <td>{{ $driver->name }}</td>
                                <td>{{ $driver->profile->phone_number ?? '--' }}</td>
                                <td>{{ $driver->profile->city ?? '--' }}</td>
                                <td class="fw-bold">{{ \App\Helpers\Helper::formatCurrency($driver->outstanding_balance) }}</td>
                                <td>
                                    @if ($driver->outstanding_balance >= $outstandingLimit)
                                        <span class="badge bg-label-danger">{{ __('Over Limit') }}</span>
                                    @else
                                        <span class="badge bg-label-success">{{ __('OK') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('dashboard.driver-balances.show', $driver->id) }}" class="btn btn-sm btn-primary">{{ __('View Ledger') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">{{ __('No taxi drivers found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
