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
                <span class="badge bg-label-{{ $customer->is_active === 'active' ? 'success' : 'secondary' }}">
                    {{ ucfirst($customer->is_active) }}
                </span>
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
