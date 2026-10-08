@extends('layouts.master')

@section('title', __('Rider Cash'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Rider Cash') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Delivery Rider Cash-in-Hand') }}</h5>
                <small class="text-muted">{{ __('Limit') }}: {{ \App\Helpers\Helper::formatCurrency($cashLimit) }} -- {{ __('riders at or above this are blocked from new offers until they settle.') }}</small>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Rider') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('City') }}</th>
                            <th>{{ __('Balance') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riders as $rider)
                            <tr>
                                <td>{{ $rider->name }}</td>
                                <td>{{ $rider->profile->phone_number ?? '--' }}</td>
                                <td>{{ $rider->profile->city ?? '--' }}</td>
                                <td class="fw-bold">{{ \App\Helpers\Helper::formatCurrency($rider->cash_balance) }}</td>
                                <td>
                                    @if ($rider->cash_balance >= $cashLimit)
                                        <span class="badge bg-label-danger">{{ __('Over Limit') }}</span>
                                    @else
                                        <span class="badge bg-label-success">{{ __('OK') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('dashboard.rider-cash.show', $rider->id) }}" class="btn btn-sm btn-primary">{{ __('View Ledger') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">{{ __('No delivery riders found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
