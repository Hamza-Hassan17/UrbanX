@extends('layouts.master')

@section('title', __('Pending Verifications'))

@section('css')
@endsection

@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.drivers.index') }}">{{ __('Drivers') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Pending Verifications') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <h5 class="card-header">{{ __('Drivers Awaiting Verification') }}</h5>
            <div class="card-datatable table-responsive">
                <table class="table border-top {{ $drivers->isNotEmpty() ? 'custom-datatables' : '' }}">
                    <thead>
                        <tr>
                            <th>{{ __('Sr.') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Phone Number') }}</th>
                            <th>{{ __('Submitted At') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($drivers as $index => $driver)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $driver->name }}</td>
                                <td>{{ $driver->email }}</td>
                                <td>{{ $driver->profile->phone_number ?? 'N/A' }}</td>
                                <td>{{ $driver->driverVerification->submitted_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                                <td class="d-flex">
                                    <a href="{{ route('dashboard.drivers.show', $driver->id) }}"
                                        class="btn btn-icon btn-text-warning waves-effect waves-light rounded-pill me-1"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="{{ __('Review Documents') }}">
                                        <i class="ti ti-eye ti-md"></i>
                                    </a>
                                    @can(['update driver'])
                                        <form action="{{ route('dashboard.drivers.verification.approve', $driver->id) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                class="btn btn-icon btn-text-success waves-effect waves-light rounded-pill me-1"
                                                data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Approve') }}">
                                                <i class="ti ti-check ti-md"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-icon btn-text-danger waves-effect waves-light rounded-pill"
                                            data-bs-toggle="modal" data-bs-target="#rejectModal{{ $driver->id }}"
                                            title="{{ __('Reject') }}">
                                            <i class="ti ti-x ti-md"></i>
                                        </button>

                                        <div class="modal fade" id="rejectModal{{ $driver->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <form action="{{ route('dashboard.drivers.verification.reject', $driver->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">{{ __('Reject Verification') }} &mdash; {{ $driver->name }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <label class="form-label">{{ __('Reason (shown to the driver)') }}</label>
                                                            <textarea name="rejection_reason" class="form-control" rows="3" required
                                                                placeholder="e.g. Vehicle registration paper is unclear, please resubmit."></textarea>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                                            <button type="submit" class="btn btn-danger">{{ __('Reject') }}</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">{{ __('No pending verifications.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
