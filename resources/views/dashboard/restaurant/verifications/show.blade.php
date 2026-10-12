@extends('layouts.master')

@section('title', __('Restaurant Verification'))

@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.restaurant-verifications.index') }}">{{ __('Restaurant Verifications') }}</a></li>
    <li class="breadcrumb-item active">{{ $verification->restaurant->name ?? 'N/A' }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ $verification->restaurant->name ?? 'N/A' }}</h5>
                        <small class="text-muted">{{ __('Submitted') }}: {{ $verification->submitted_at?->format('d M, Y h:i A') ?? '--' }}</small>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="info-label">{{ __('NTN Number') }}</h6>
                                <p class="info-value">{{ $verification->ntn_number ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="info-label">{{ __('Bank Account Title') }}</h6>
                                <p class="info-value">{{ $verification->bank_account_title ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="info-label">{{ __('Bank IBAN') }}</h6>
                                <p class="info-value">{{ $verification->bank_iban ?? 'N/A' }}</p>
                            </div>
                        </div>
                        <h6 class="mb-3">{{ __('Documents') }}</h6>
                        <div class="row">
                            @foreach ([
                                'owner_cnic_front' => 'Owner CNIC (Front)',
                                'owner_cnic_back' => 'Owner CNIC (Back)',
                                'food_authority_license' => 'Food Authority Licence',
                                'logo' => 'Logo',
                                'storefront_photo' => 'Storefront Photo',
                            ] as $field => $label)
                                <div class="col-md-4 mb-3">
                                    <h6 class="info-label">{{ __($label) }}</h6>
                                    @if ($verification->{$field})
                                        <a href="{{ asset('storage/' . $verification->{$field}) }}" target="_blank">
                                            <img src="{{ asset('storage/' . $verification->{$field}) }}" class="img-fluid rounded border" style="max-height: 150px;" alt="{{ $label }}">
                                        </a>
                                    @else
                                        <p class="text-muted">{{ __('Not uploaded') }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if ($verification->status === 'rejected' && $verification->rejection_reason)
                            <div class="alert alert-danger mt-3">
                                <strong>{{ __('Rejection Reason') }}:</strong> {{ $verification->rejection_reason }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Decision') }}</h5>
                    </div>
                    <div class="card-body">
                        <p>{{ __('Current status') }}: <strong>{{ ucfirst($verification->status) }}</strong></p>
                        @if ($verification->status === 'submitted')
                            @can(['update restaurant'])
                                <form method="POST" action="{{ route('dashboard.restaurant-verifications.approve', $verification->id) }}" class="mb-3"
                                    onsubmit="return confirm('{{ __('Approve this restaurant? It will become visible to customers.') }}');">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">{{ __('Approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('dashboard.restaurant-verifications.reject', $verification->id) }}">
                                    @csrf
                                    <div class="mb-2">
                                        <label class="form-label">{{ __('Rejection Reason') }}</label><span class="text-danger">*</span>
                                        <textarea class="form-control @error('rejection_reason') is-invalid @enderror" name="rejection_reason" rows="3" required></textarea>
                                        @error('rejection_reason')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>
                                    <button type="submit" class="btn btn-danger w-100">{{ __('Reject') }}</button>
                                </form>
                            @endcan
                        @else
                            <p class="text-muted">{{ __('Reviewed by') }}: {{ $verification->reviewer->name ?? '--' }} {{ $verification->reviewed_at?->format('d M, Y h:i A') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
