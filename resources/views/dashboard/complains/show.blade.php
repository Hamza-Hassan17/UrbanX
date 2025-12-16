@extends('layouts.master')

@section('title', __('Complain Details'))

@section('breadcrumb-items')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard.complains.index') }}">{{ __('Complains') }}</a>
    </li>
    <li class="breadcrumb-item active">{{ __('Show') }}</li>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    <!-- Header Card -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="mb-1">
                        <i class="ti ti-message-exclamation text-primary me-1"></i>
                        Complain #{{ $complain->id }}
                    </h4>
                    <small class="text-muted">
                        <i class="ti ti-calendar-event me-1"></i>
                        {{ $complain->created_at->format('d M Y, h:i A') }}
                    </small>
                </div>

                <span class="badge fs-6 px-3 py-2 bg-label-{{
                    $complain->status == 'resolved' ? 'success' :
                    ($complain->status == 'pending' ? 'primary' :
                    ($complain->status == 'inprogress' ? 'warning' : 'secondary'))
                }}">
                    <i class="ti ti-circle-check me-1"></i>
                    {{ ucfirst($complain->status) }}
                </span>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="row g-4">

        <!-- Customer Info -->
        <div class="col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title text-primary mb-3">
                        <i class="ti ti-user me-1"></i> Customer Information
                    </h6>

                    <div class="mb-3">
                        <label class="text-muted small">Name</label>
                        <p class="fw-semibold mb-0">{{ $complain->name }}</p>
                    </div>

                    <div>
                        <label class="text-muted small">Subject</label>
                        <p class="fw-semibold mb-0">{{ $complain->subject }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Complaint Details -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="card-title text-primary mb-3">
                        <i class="ti ti-file-text me-1"></i> Complaint Details
                    </h6>

                    <div class="bg-light rounded p-3">
                        <p class="mb-0 lh-lg">
                            {{ $complain->complain_text }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action -->
        <div class="col-12 text-end">
            <a href="{{ route('dashboard.complains.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-left me-1"></i> Back to Complains
            </a>
        </div>

    </div>
</div>
@endsection
