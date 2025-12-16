@extends('layouts.master')

@section('title', __('Chauffeurs Vehicle Details'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item"><a
            href="{{ route('dashboard.chauffeur-vehicles.index') }}">{{ __('Chauffeurs Vehicles') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Show') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-6">
            <div class="card-body pt-4">
                <div class="row">
                    {{-- LEFT: Main Image --}}
                    <div class="col-lg-4 mb-4">
                        <div class="border rounded p-2 text-center">
                            @if($vehicle->main_image)
                                <img src="{{ asset($vehicle->main_image) }}"
                                    class="img-fluid rounded"
                                    alt="{{ $vehicle->title ?? $vehicle->car_model }}">
                            @else
                                <div class="border rounded p-4 text-center bg-light">
                                    <i class="ti ti-car text-secondary" style="font-size: 80px;"></i>
                                    <div class="mt-2 text-muted">No Image Available</div>
                                </div>
                            @endif
                        </div>

                        <div class="mt-3 text-center">
                            <span class="badge bg-{{ $vehicle->is_active == 'active' ? 'success' : 'danger' }}">
                                {{ ucfirst($vehicle->is_active) }}
                            </span>

                            @if ($vehicle->is_featured == '1')
                                <span class="badge bg-warning text-dark ms-2">Featured</span>
                            @endif
                        </div>
                    </div>

                    {{-- RIGHT: Vehicle Info --}}
                    <div class="col-lg-8">
                        <h4 class="mb-2">
                            {{ $vehicle->title ?? $vehicle->car_model }}
                        </h4>

                        <p class="text-muted mb-3">
                            {{ $vehicle->carBrand->name ?? '—' }} • {{ $vehicle->year ?? 'N/A' }}
                        </p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <strong>Car Model:</strong>
                                <div>{{ $vehicle->car_model }}</div>
                            </div>

                            <div class="col-md-6">
                                <strong>Fuel Type:</strong>
                                <div>{{ ucfirst($vehicle->car_fuel_type) }}</div>
                            </div>

                            <div class="col-md-6">
                                <strong>Transmission:</strong>
                                <div>{{ ucfirst($vehicle->transmission) }}</div>
                            </div>

                            <div class="col-md-6">
                                <strong>Color:</strong>
                                <div>{{ $vehicle->color ?? '—' }}</div>
                            </div>

                            <div class="col-md-6">
                                <strong>Seats:</strong>
                                <div>{{ $vehicle->seats ?? '—' }}</div>
                            </div>

                            <div class="col-md-6">
                                <strong>Address:</strong>
                                <div>{{ $vehicle->address ?? '—' }}</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        {{-- PRICING --}}
                        <h5 class="mb-3">Pricing</h5>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted">Per Hour</small>
                                    <div class="fw-bold">
                                        {{ number_format($vehicle->price_per_hour, 2) }}
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted">Per Day</small>
                                    <div class="fw-bold">
                                        {{ number_format($vehicle->price_per_day, 2) }}
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted">Per Week</small>
                                    <div class="fw-bold">
                                        {{ number_format($vehicle->price_per_week, 2) }}
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted">Per Month</small>
                                    <div class="fw-bold">
                                        {{ number_format($vehicle->price_per_month, 2) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- DESCRIPTION --}}
                        @if ($vehicle->description)
                            <hr class="my-4">
                            <h5>Description</h5>
                            <p class="text-muted">
                                {{ $vehicle->description }}
                            </p>
                        @endif

                        {{-- ACTIONS --}}
                        <div class="mt-4">
                            <a href="{{ route('dashboard.chauffeur-vehicles.index') }}" class="btn btn-secondary">
                                ← Back
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@section('script')
@endsection
