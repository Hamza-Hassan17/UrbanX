@extends('layouts.master')

@section('title', __('Support Requests'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Support Requests') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Driver Support Requests') }}</h5>
                <div class="btn-group">
                    <a href="{{ route('dashboard.support-requests.index') }}" class="btn btn-sm {{ !$status ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('All') }}</a>
                    <a href="{{ route('dashboard.support-requests.index', ['status' => 'pending']) }}" class="btn btn-sm {{ $status === 'pending' ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('Pending') }}</a>
                    <a href="{{ route('dashboard.support-requests.index', ['status' => 'approved']) }}" class="btn btn-sm {{ $status === 'approved' ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('Approved') }}</a>
                    <a href="{{ route('dashboard.support-requests.index', ['status' => 'rejected']) }}" class="btn btn-sm {{ $status === 'rejected' ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('Rejected') }}</a>
                    <a href="{{ route('dashboard.support-requests.index', ['status' => 'closed']) }}" class="btn btn-sm {{ $status === 'closed' ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('Closed') }}</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table border-top">
                    <thead>
                        <tr>
                            <th>{{ __('Sr.') }}</th>
                            <th>{{ __('Driver') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Submitted At') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $index => $req)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $req->driver->name ?? 'N/A' }}</td>
                                <td>{{ $req->subject }}</td>
                                <td>
                                    @php
                                        $badgeClass = [
                                            'pending' => 'bg-label-warning',
                                            'approved' => 'bg-label-success',
                                            'rejected' => 'bg-label-danger',
                                            'closed' => 'bg-label-secondary',
                                        ][$req->status] ?? 'bg-label-secondary';
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">{{ ucfirst($req->status) }}</span>
                                </td>
                                <td>{{ $req->created_at->format('M d, Y h:i A') }}</td>
                                <td>
                                    <a href="{{ route('dashboard.support-requests.show', $req->id) }}"
                                        class="btn btn-icon btn-text-warning waves-effect waves-light rounded-pill"
                                        data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('View / Respond') }}">
                                        <i class="ti ti-eye ti-md"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">{{ __('No support requests.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
