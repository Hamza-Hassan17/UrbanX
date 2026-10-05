@extends('layouts.master')

@section('title', __('Finance'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Finance') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        @include('dashboard.finance.partials.tabs')

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Tax & Commission') }}</h5>
                <small class="text-muted">{{ __('Applied to every completed ride when calculating the driver\'s net income.') }}</small>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('dashboard.finance.tax-commission.update') }}" class="row g-3">
                    @csrf
                    @method('PUT')

                    <div class="col-md-6">
                        <label for="driver_commission_percent" class="form-label">{{ __('Service Commission (%)') }}</label><span class="text-danger">*</span>
                        <small class="fw-medium text-primary d-block">({{ __('Platform commission deducted from the gross ride fare') }})</small>
                        <input class="form-control @error('driver_commission_percent') is-invalid @enderror" type="number"
                            step="0.01" min="0" max="100" id="driver_commission_percent" name="driver_commission_percent"
                            value="{{ old('driver_commission_percent', $commissionPercent) }}" required/>
                        @error('driver_commission_percent')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="sst_percent" class="form-label">{{ __('SST (%)') }}</label><span class="text-danger">*</span>
                        <small class="fw-medium text-primary d-block">({{ __('Sales tax applied on the commission and backed out of the driver\'s share') }})</small>
                        <input class="form-control @error('sst_percent') is-invalid @enderror" type="number"
                            step="0.01" min="0" max="100" id="sst_percent" name="sst_percent"
                            value="{{ old('sst_percent', $sstPercent) }}" required/>
                        @error('sst_percent')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
