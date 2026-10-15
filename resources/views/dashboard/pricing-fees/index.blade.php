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
                        <label for="platform_share_amount" class="form-label">{{ __('Platform Share of Delivery Fee (PKR)') }}</label><span class="text-danger">*</span>
                        <small class="fw-medium text-primary d-block">({{ __('Platform\'s flat cut of the delivery fee, for delivery riders') }})</small>
                        <input class="form-control @error('platform_share_amount') is-invalid @enderror" type="number"
                            step="0.01" min="0" id="platform_share_amount" name="platform_share_amount"
                            value="{{ old('platform_share_amount', $settings->platform_share_amount) }}" required/>
                        @error('platform_share_amount')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="rider_cash_limit" class="form-label">{{ __('Rider Cash-in-Hand Limit') }}</label><span class="text-danger">*</span>
                        <small class="fw-medium text-primary d-block">({{ __('Riders at or above this COD balance get no new offers until they settle') }})</small>
                        <input class="form-control @error('rider_cash_limit') is-invalid @enderror" type="number"
                            step="0.01" min="0" id="rider_cash_limit" name="rider_cash_limit"
                            value="{{ old('rider_cash_limit', $settings->rider_cash_limit) }}" required/>
                        @error('rider_cash_limit')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="sst_ride_fare_method" class="form-label">{{ __('SST on Ride Fare -- Calculation Method') }}</label><span class="text-danger">*</span>
                        <small class="fw-medium text-primary d-block">({{ __('Not yet confirmed with the client -- default keeps current behaviour') }})</small>
                        <select class="form-select @error('sst_ride_fare_method') is-invalid @enderror" id="sst_ride_fare_method" name="sst_ride_fare_method" required>
                            <option value="inclusive" {{ old('sst_ride_fare_method', $settings->sst_ride_fare_method ?? 'inclusive') == 'inclusive' ? 'selected' : '' }}>
                                {{ __('On gross fare (tax-inclusive) -- current default') }}
                            </option>
                            <option value="exclusive" {{ old('sst_ride_fare_method', $settings->sst_ride_fare_method ?? 'inclusive') == 'exclusive' ? 'selected' : '' }}>
                                {{ __('On driver/rider share after commission') }}
                            </option>
                        </select>
                        @error('sst_ride_fare_method')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="col-12">
                        <hr class="mt-2 mb-3">
                        <h6 class="mb-3">{{ __('Dispatch') }}</h6>
                    </div>
                    <div class="col-md-3">
                        <label for="offer_acceptance_timeout_seconds" class="form-label">{{ __('Offer Acceptance Timeout (seconds)') }}</label><span class="text-danger">*</span>
                        <input class="form-control @error('offer_acceptance_timeout_seconds') is-invalid @enderror" type="number"
                            min="5" id="offer_acceptance_timeout_seconds" name="offer_acceptance_timeout_seconds"
                            value="{{ old('offer_acceptance_timeout_seconds', $settings->offer_acceptance_timeout_seconds ?? 30) }}" required/>
                        @error('offer_acceptance_timeout_seconds')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="max_reoffer_attempts" class="form-label">{{ __('Max Re-offer Attempts') }}</label><span class="text-danger">*</span>
                        <small class="fw-medium text-primary d-block">({{ __('Before a job is flagged as stale') }})</small>
                        <input class="form-control @error('max_reoffer_attempts') is-invalid @enderror" type="number"
                            min="1" id="max_reoffer_attempts" name="max_reoffer_attempts"
                            value="{{ old('max_reoffer_attempts', $settings->max_reoffer_attempts ?? 5) }}" required/>
                        @error('max_reoffer_attempts')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="taxi_outstanding_limit" class="form-label">{{ __('Taxi Driver Outstanding Limit') }}</label><span class="text-danger">*</span>
                        <small class="fw-medium text-primary d-block">({{ __('Commission+SST owed on cash fares') }})</small>
                        <input class="form-control @error('taxi_outstanding_limit') is-invalid @enderror" type="number"
                            step="0.01" min="0" id="taxi_outstanding_limit" name="taxi_outstanding_limit"
                            value="{{ old('taxi_outstanding_limit', $settings->taxi_outstanding_limit ?? 5000) }}" required/>
                        @error('taxi_outstanding_limit')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="stale_unassigned_minutes" class="form-label">{{ __('Stale: Unassigned Job (minutes)') }}</label><span class="text-danger">*</span>
                        <input class="form-control @error('stale_unassigned_minutes') is-invalid @enderror" type="number"
                            min="1" id="stale_unassigned_minutes" name="stale_unassigned_minutes"
                            value="{{ old('stale_unassigned_minutes', $settings->stale_unassigned_minutes ?? 5) }}" required/>
                        @error('stale_unassigned_minutes')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="stale_restaurant_accept_minutes" class="form-label">{{ __('Stale: Restaurant Not Accepting (minutes)') }}</label><span class="text-danger">*</span>
                        <input class="form-control @error('stale_restaurant_accept_minutes') is-invalid @enderror" type="number"
                            min="1" id="stale_restaurant_accept_minutes" name="stale_restaurant_accept_minutes"
                            value="{{ old('stale_restaurant_accept_minutes', $settings->stale_restaurant_accept_minutes ?? 5) }}" required/>
                        @error('stale_restaurant_accept_minutes')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="stale_ready_for_pickup_minutes" class="form-label">{{ __('Stale: Rider Not Picking Up (minutes)') }}</label><span class="text-danger">*</span>
                        <input class="form-control @error('stale_ready_for_pickup_minutes') is-invalid @enderror" type="number"
                            min="1" id="stale_ready_for_pickup_minutes" name="stale_ready_for_pickup_minutes"
                            value="{{ old('stale_ready_for_pickup_minutes', $settings->stale_ready_for_pickup_minutes ?? 10) }}" required/>
                        @error('stale_ready_for_pickup_minutes')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="allow_legacy_direct_accept" name="allow_legacy_direct_accept" value="1"
                                {{ old('allow_legacy_direct_accept', $settings->allow_legacy_direct_accept ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="allow_legacy_direct_accept">{{ __('Allow Legacy Direct Accept') }}</label>
                        </div>
                        <small class="text-muted d-block">{{ __('When on (default), accept-ride still works for an app build that taps Accept without ever polling for a formal offer -- the pre-Batch-2 behaviour. Turn off once updated app builds (which poll and see the offer countdown) are rolled out, so accept-ride then requires a valid, unexpired offer for that specific driver.') }}</small>
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
