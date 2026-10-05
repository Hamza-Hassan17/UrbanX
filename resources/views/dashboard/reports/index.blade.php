@extends('layouts.master')

@section('title', __('Reports'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Reports') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Admin Panel User Activity') }}</h5>
                <small class="text-muted">{{ __('Rides created and status changes made from the dashboard, per admin panel user (super admin, admin, manager, operator, etc.).') }}</small>
            </div>
            <div class="table-responsive">
                <table class="table border-top">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Role') }}</th>
                            <th>{{ __('Rides Created') }}</th>
                            <th>{{ __('Status Changes Made') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($operatorStats as $operator)
                            <tr>
                                <td>{{ $operator->name }}</td>
                                <td><span class="badge bg-label-primary">{{ $operator->role_label }}</span></td>
                                <td>{{ $operator->rides_created }}</td>
                                <td>{{ $operator->status_changes_made }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">{{ __('No admin panel activity recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
