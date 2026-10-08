@extends('layouts.master')

@section('title', __('Pricing & Fees'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Pricing & Fees') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        @include('dashboard.finance.partials.tabs')

        <div class="card mb-6">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Delivery & Commission Pricing') }}</h5>
                <small class="text-muted">{{ __('Distance charged is always rounded UP to the next whole km (e.g. 3.4 km charges as 4 km).') }}</small>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('dashboard.pricing-fees.update') }}" class="row g-3">
                    @csrf
                    @method('PUT')

                    <div class="col-12">
                        <h6 class="text-primary">{{ __('Food Delivery Fee') }}</h6>
                    </div>
                    <div class="col-md-4">
                        <label for="food_first_km_fee" class="form-label">{{ __('First KM Fee') }}</label><span class="text-danger">*</span>
                        <input class="form-control @error('food_first_km_fee') is-invalid @enderror" type="number"
                            step="0.01" min="0" id="food_first_km_fee" name="food_first_km_fee"
                            value="{{ old('food_first_km_fee', $settings->food_first_km_fee) }}" required/>
                        @error('food_first_km_fee')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="food_per_km_fee" class="form-label">{{ __('Per KM Fee (after first km)') }}</label><span class="text-danger">*</span>
                        <input class="form-control @error('food_per_km_fee') is-invalid @enderror" type="number"
                            step="0.01" min="0" id="food_per_km_fee" name="food_per_km_fee"
                            value="{{ old('food_per_km_fee', $settings->food_per_km_fee) }}" required/>
                        @error('food_per_km_fee')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="food_max_distance_km" class="form-label">{{ __('Max Delivery Distance (km)') }}</label><span class="text-danger">*</span>
                        <input class="form-control @error('food_max_distance_km') is-invalid @enderror" type="number"
                            step="1" min="1" id="food_max_distance_km" name="food_max_distance_km"
                            value="{{ old('food_max_distance_km', $settings->food_max_distance_km) }}" required/>
                        @error('food_max_distance_km')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="col-12 mt-5">
                        <h6 class="text-primary">{{ __('Parcel Delivery Fee') }}</h6>
                    </div>
                    <div class="col-md-4">
                        <label for="parcel_first_km_fee" class="form-label">{{ __('First KM Fee') }}</label><span class="text-danger">*</span>
                        <input class="form-control @error('parcel_first_km_fee') is-invalid @enderror" type="number"
                            step="0.01" min="0" id="parcel_first_km_fee" name="parcel_first_km_fee"
                            value="{{ old('parcel_first_km_fee', $settings->parcel_first_km_fee) }}" required/>
                        @error('parcel_first_km_fee')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="parcel_per_km_fee" class="form-label">{{ __('Per KM Fee (after first km)') }}</label><span class="text-danger">*</span>
                        <input class="form-control @error('parcel_per_km_fee') is-invalid @enderror" type="number"
                            step="0.01" min="0" id="parcel_per_km_fee" name="parcel_per_km_fee"
                            value="{{ old('parcel_per_km_fee', $settings->parcel_per_km_fee) }}" required/>
                        @error('parcel_per_km_fee')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="parcel_max_distance_km" class="form-label">{{ __('Max Delivery Distance (km)') }}</label><span class="text-danger">*</span>
                        <input class="form-control @error('parcel_max_distance_km') is-invalid @enderror" type="number"
                            step="1" min="1" id="parcel_max_distance_km" name="parcel_max_distance_km"
                            value="{{ old('parcel_max_distance_km', $settings->parcel_max_distance_km) }}" required/>
                        @error('parcel_max_distance_km')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="col-12 mt-5">
                        <h6 class="text-primary">{{ __('Commission & Platform Share') }}</h6>
                    </div>
                    <div class="col-md-4">
                        <label for="restaurant_commission_percent" class="form-label">{{ __('Restaurant Commission (%)') }}</label><span class="text-danger">*</span>
                        <small class="fw-medium text-primary d-block">({{ __('Charged on the full food price, before any discount') }})</small>
                        <input class="form-control @error('restaurant_commission_percent') is-invalid @enderror" type="number"
                            step="0.01" min="0" max="100" id="restaurant_commission_percent" name="restaurant_commission_percent"
                            value="{{ old('restaurant_commission_percent', $settings->restaurant_commission_percent) }}" required/>
                        @error('restaurant_commission_percent')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="platform_share_percent" class="form-label">{{ __('Platform Share of Delivery Fee (%)') }}</label><span class="text-danger">*</span>
                        <small class="fw-medium text-primary d-block">({{ __('Platform\'s cut of the delivery fee, for delivery riders') }})</small>
                        <input class="form-control @error('platform_share_percent') is-invalid @enderror" type="number"
                            step="0.01" min="0" max="100" id="platform_share_percent" name="platform_share_percent"
                            value="{{ old('platform_share_percent', $settings->platform_share_percent) }}" required/>
                        @error('platform_share_percent')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="rider_cash_limit" class="form-label">{{ __('Rider Cash-in-Hand Limit') }}</label><span class="text-danger">*</span>
                        <small class="fw-medium text-primary d-block">({{ __('Riders at or above this COD balance get no new offers until they settle') }})</small>
                        <input class="form-control @error('rider_cash_limit') is-invalid @enderror" type="number"
                            step="0.01" min="0" id="rider_cash_limit" name="rider_cash_limit"
                            value="{{ old('rider_cash_limit', $settings->rider_cash_limit) }}" required/>
                        @error('rider_cash_limit')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="col-12">
                        <small class="text-muted d-block mb-2">{{ __('SST on driver/rider income is shared with the Tax & Commission page\'s "SST on Ride Fare" setting -- applies to both taxi drivers and delivery riders.') }}</small>
                        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Recent Changes') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Admin') }}</th>
                            <th>{{ __('Field') }}</th>
                            <th>{{ __('Old Value') }}</th>
                            <th>{{ __('New Value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @foreach ($log->new_values as $field => $newValue)
                                <tr>
                                    <td>{{ $log->created_at->format('d M, Y h:i A') }}</td>
                                    <td>{{ $log->admin->name ?? 'N/A' }}</td>
                                    <td>{{ $field }}</td>
                                    <td>{{ $log->old_values[$field] ?? '-' }}</td>
                                    <td>{{ $newValue }}</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">{{ __('No changes yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
