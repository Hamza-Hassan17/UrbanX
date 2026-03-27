@extends('layouts.master')

@section('title', __('Create Restaurant Voucher'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.restaurant-vouchers.index') }}">{{ __('Restaurant Vouchers') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Create') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-6">
            <!-- Account -->
            <div class="card-body pt-4">
                <form method="POST" action="{{ route('dashboard.restaurant-vouchers.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row p-5">
                        <h3>{{ __('Add New Voucher') }}</h3>
                        <div class="mb-4 col-md-6">
                            <label for="code" class="form-label">{{ __('Code') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('code') is-invalid @enderror" type="text" id="code"
                                name="code" required placeholder="{{ __('Enter code') }}" autofocus
                                value="{{ old('code') }}" />
                            @error('code')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-6">
                            <label for="discount_type" class="form-label">{{ __('Discount Type') }}<span
                                class="text-danger">*</span></label>
                            <select id="discount_type" name="discount_type" class="select2 form-select @error('discount_type') is-invalid @enderror" required>
                                <option value="" selected disabled>{{ __('Select discount type') }}</option>
                                <option value="fixed" {{ old('discount_type') == 'fixed' ? 'selected' : '' }}>{{__('Fixed')}}</option>
                                <option value="percentage" {{ old('discount_type') == 'percentage' ? 'selected' : '' }}>{{__('Percentage')}}</option>
                            </select>
                            @error('discount_type')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-6">
                            <label for="discount_amount" class="form-label">{{ __('Discount Amount') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('discount_amount') is-invalid @enderror" type="integer" step="any" id="discount_amount"
                                name="discount_amount" required placeholder="{{ __('Enter discount amount') }}"
                                value="{{ old('discount_amount') }}" />
                            @error('discount_amount')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-6">
                            <label for="minimum_purchase" class="form-label">{{ __('Minimum Purchase') }}</label>
                            <input class="form-control @error('minimum_purchase') is-invalid @enderror" type="integer" step="any" id="minimum_purchase"
                                name="minimum_purchase" placeholder="{{ __('Enter minimum purchase') }}"
                                value="{{ old('minimum_purchase') }}" />
                            @error('minimum_purchase')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-6">
                            <label for="per_user_limit" class="form-label">{{ __('Per User Limit') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('per_user_limit') is-invalid @enderror" type="integer" id="per_user_limit"
                                name="per_user_limit" required placeholder="{{ __('Enter per user limit') }}"
                                value="{{ old('per_user_limit') }}" />
                            @error('per_user_limit')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-6">
                            <label for="expires_at" class="form-label">{{ __('Expiry Date') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('expires_at') is-invalid @enderror" type="date" id="expires_at"
                                name="expires_at" required placeholder="{{ __('Enter expiry date') }}"
                                value="{{ old('expires_at') }}" />
                            @error('expires_at')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-12">
                            <label for="description" class="form-label">{{ __('Description') }}</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                placeholder="{{ __('Enter description') }}" cols="30" rows="10">{{ old('description') }}</textarea>
                            @error('description')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary me-3">{{ __('Add Voucher') }}</button>
                    </div>
                </form>
            </div>
            <!-- /Account -->
        </div>
    </div>
@endsection

@section('script')
@endsection
