@extends('layouts.master')

@section('title', __('Restaurant Verifications'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Restaurant Verifications') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Restaurant Verifications') }}</h5>
                <div class="btn-group">
                    @foreach (['submitted' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                        <a href="{{ route('dashboard.restaurant-verifications.index', ['status' => $key]) }}"
                            class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ __($label) }}</a>
                    @endforeach
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Restaurant') }}</th>
                            <th>{{ __('Submitted') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Reviewed By') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($verifications as $verification)
                            <tr>
                                <td>{{ $verification->restaurant->name ?? 'N/A' }}</td>
                                <td>{{ $verification->submitted_at?->format('d M, Y h:i A') ?? '--' }}</td>
                                <td>
                                    @if ($verification->status === 'submitted')
                                        <span class="badge bg-label-warning">{{ __('Pending') }}</span>
                                    @elseif ($verification->status === 'approved')
                                        <span class="badge bg-label-success">{{ __('Approved') }}</span>
                                    @elseif ($verification->status === 'rejected')
                                        <span class="badge bg-label-danger">{{ __('Rejected') }}</span>
                                    @else
                                        <span class="badge bg-label-secondary">{{ __('Not Submitted') }}</span>
                                    @endif
                                </td>
                                <td>{{ $verification->reviewer->name ?? '--' }}</td>
                                <td>
                                    <a href="{{ route('dashboard.restaurant-verifications.show', $verification->id) }}" class="btn btn-sm btn-primary">{{ __('Review') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">{{ __('No verifications found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">
                {{ $verifications->links() }}
            </div>
        </div>
    </div>
@endsection
