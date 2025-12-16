@extends('layouts.master')

@section('title', __('Chauffeurs Booking Details'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item"><a
            href="{{ route('dashboard.chauffeur-bookings.index') }}">{{ __('Chauffeurs Bookings') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Show') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-6">
            <div class="card-body">

                {{-- HEADER --}}
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="mb-1">
                            Booking #{{ $booking->booking_id }}
                        </h4>
                        <small class="text-muted">
                            <i class="ti ti-calendar me-1"></i>
                            {{ $booking->created_at->format('d M Y, h:i A') }}
                        </small>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-label-{{ $booking->status == 'confirmed'
                                ? 'success'
                                : ($booking->status == 'completed'
                                    ? 'primary'
                                    : ($booking->status == 'cancelled'
                                        ? 'danger'
                                        : 'warning')) }}">
                            {{ ucfirst($booking->status) }}
                        </span>

                        {{-- DOWNLOAD RECEIPT --}}
                        @if ($booking->transaction)
                            <a href="{{ route('dashboard.chauffeur-bookings.download-receipt', $booking->id) }}"
                                class="btn btn-sm btn-primary">
                                <i class="ti ti-download me-1"></i> Receipt
                            </a>
                        @endif
                    </div>
                </div>

                <div class="row g-4">

                    {{-- CUSTOMER --}}
                    <div class="col-lg-4">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3 text-primary">
                                <i class="ti ti-user me-1"></i> Customer
                            </h6>
                            <p class="mb-1"><strong>Name:</strong> {{ $booking->name }}</p>
                            <p class="mb-1"><strong>Email:</strong> {{ $booking->email }}</p>
                            <p class="mb-0"><strong>Phone:</strong> {{ $booking->phone ?? '—' }}</p>
                        </div>
                    </div>

                    {{-- VEHICLE --}}
                    <div class="col-lg-4">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3 text-primary">
                                <i class="ti ti-car me-1"></i> Vehicle
                            </h6>
                            <p class="mb-1">
                                <strong>Name:</strong>
                                <a href="{{ route('dashboard.chauffeur-vehicles.show', $booking->vehicle_id) }}">{{ $booking->vehicle->title ?? $booking->vehicle->car_model }}</a>
                            </p>
                            <p class="mb-1">
                                <strong>Rent Type:</strong> {{ ucfirst($booking->rent_type) }}
                            </p>
                            <p class="mb-0">
                                <strong>With Driver:</strong>
                                <span class="badge bg-label-{{ $booking->with_driver == '1' ? 'success' : 'secondary' }}">
                                    {{ $booking->with_driver == '1' ? 'Yes' : 'No' }}
                                </span>
                            </p>
                        </div>
                    </div>

                    {{-- TIME --}}
                    <div class="col-lg-4">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3 text-primary">
                                <i class="ti ti-clock me-1"></i> Schedule
                            </h6>
                            <p class="mb-2">
                                <strong>Start:</strong><br>
                                {{ \Carbon\Carbon::parse($booking->start_time)->format('d M Y, h:i A') }}
                            </p>
                            <p class="mb-0">
                                <strong>End:</strong><br>
                                {{ \Carbon\Carbon::parse($booking->end_time)->format('d M Y, h:i A') }}
                            </p>
                        </div>
                    </div>

                    {{-- LOCATIONS --}}
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3 text-primary">
                                <i class="ti ti-map-pin me-1"></i> Locations
                            </h6>
                            <p class="mb-1"><strong>Pickup:</strong> {{ $booking->pickup_location ?? '—' }}</p>
                            <p class="mb-0"><strong>Dropoff:</strong> {{ $booking->dropoff_location ?? '—' }}</p>
                        </div>
                    </div>

                    {{-- PAYMENT --}}
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3 text-primary">
                                <i class="ti ti-receipt me-1"></i> Payment
                            </h6>

                            <div class="d-flex justify-content-between mb-1">
                                <span>Subtotal</span>
                                <strong>{{ number_format($booking->subtotal, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Discount</span>
                                <strong>{{ number_format($booking->discount, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax</span>
                                <strong>{{ number_format($booking->tax, 2) }}</strong>
                            </div>

                            <hr class="my-2">

                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Total</span>
                                <span class="fw-bold">
                                    {{ number_format($booking->total_amount, 2) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- TRANSACTION --}}
                    <div class="col-lg-12">
                        <div class="border rounded p-3">
                            <h6 class="mb-3 text-primary">
                                <i class="ti ti-credit-card me-1"></i> Transaction
                            </h6>

                            @if ($booking->transaction)
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>TRX ID</strong><br>
                                        {{ $booking->transaction->trx_id }}
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Method</strong><br>
                                        {{ ucfirst($booking->transaction->payment_method) }}
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Status</strong><br>
                                        <span
                                            class="badge bg-label-{{ $booking->transaction->payment_status == 'complete'
                                    ? 'success'
                                    : ($booking->transaction->payment_status == 'failed'
                                        ? 'danger'
                                        : 'warning') }}">
                                            {{ ucfirst($booking->transaction->payment_status) }}
                                        </span>
                                    </div>
                                </div>
                            @else
                                <span class="text-muted">
                                    <i class="ti ti-alert-circle me-1"></i> No transaction found
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- NOTES --}}
                    @if ($booking->notes || $booking->cancel_reason)
                        <div class="col-lg-12">
                            <div class="border rounded p-3">
                                <h6 class="mb-2 text-primary">
                                    <i class="ti ti-note me-1"></i> Notes
                                </h6>
                                <p class="mb-0">
                                    {{ $booking->notes ?? $booking->cancel_reason }}
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- ACTION --}}
                    <div class="col-lg-12 text-end">
                        <a href="{{ route('dashboard.chauffeur-bookings.index') }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left me-1"></i> Back
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>
@endsection

@section('script')
@endsection
