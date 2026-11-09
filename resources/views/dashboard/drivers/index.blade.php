@extends('layouts.master')

@section('title', $pageTitle ?? __('Drivers'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ $pageTitle ?? __('Drivers') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $statCards = [
                ['label' => __('Total'), 'value' => $drivers->count(), 'icon' => 'ti-users', 'color' => 'primary'],
                ['label' => __('Active'), 'value' => $drivers->where('is_active', 'active')->count(), 'icon' => 'ti-user-check', 'color' => 'success'],
                ['label' => __('Verified'), 'value' => $drivers->filter(fn ($d) => $d->driverVerification?->status === 'approved')->count(), 'icon' => 'ti-shield-check', 'color' => 'info'],
                ['label' => __('Pending Verification'), 'value' => $drivers->filter(fn ($d) => $d->driverVerification?->status === 'submitted')->count(), 'icon' => 'ti-clock', 'color' => 'warning'],
            ];
        @endphp
        <div class="row g-6 mb-6">
            @foreach ($statCards as $stat)
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body d-flex align-items-start justify-content-between">
                            <div>
                                <span class="text-heading">{{ $stat['label'] }}</span>
                                <h4 class="mb-0 mt-1">{{ $stat['value'] }}</h4>
                            </div>
                            <div class="avatar">
                                <span class="avatar-initial rounded bg-label-{{ $stat['color'] }}"><i class="ti {{ $stat['icon'] }} ti-26px"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <!-- Users List Table -->
        <div class="card">
            <div class="card-datatable table-responsive">
                <table class="datatables-users table border-top custom-datatables">
                    <thead>
                        <tr>
                            <th>{{ __('Sr.') }}</th>
                            <th>{{ $pageTitle ?? __('Driver') }}</th>
                            <th>{{ __('Phone Number') }}</th>
                            <th>{{ __('City') }}</th>
                            <th>{{ __('Created Date') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Presence') }}</th>
                            <th>{{ __('Verification') }}</th>
                            <th>{{ __('Terms Accepted') }}</th>
                            @canany(['delete driver', 'view driver', 'update driver'])<th>{{ __('Action') }}</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($drivers as $index => $driver)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>@include('dashboard.partials.person-cell', ['name' => $driver->name, 'email' => $driver->email, 'url' => auth()->user()->can('view driver') ? route('dashboard.drivers.show', $driver->id) : null])</td>
                                <td>{{ $driver->profile->phone_number }}</td>
                                <td>
                                    {{-- Read-only -- city is set automatically from the driver's
                                         location at registration, not typed in by an admin. --}}
                                    {{ $driver->profile->city ?? 'Unassigned' }}
                                </td>
                                <td class="text-nowrap">{{ $driver->created_at->format('M d, Y') }}</td>
                                <td>
                                    <span
                                        class="badge me-4 bg-label-{{ $driver->is_active == 'active' ? 'success' : 'danger' }}">{{ ucfirst($driver->is_active) }}</span>
                                </td>
                                <td>@include('dashboard.drivers.partials.presence-badge', ['driver' => $driver])</td>
                                <td>
                                    @php $verificationStatus = $driver->driverVerification->status ?? 'not_submitted'; @endphp
                                    @if ($verificationStatus === 'not_submitted')
                                        <span class="badge bg-label-secondary">{{ __('Not Submitted') }}</span>
                                    @elseif ($verificationStatus === 'submitted')
                                        <span class="badge bg-label-warning">{{ __('Pending') }}</span>
                                    @elseif ($verificationStatus === 'approved')
                                        <span class="badge bg-label-success">{{ __('Verified') }}</span>
                                    @elseif ($verificationStatus === 'rejected')
                                        <span class="badge bg-label-danger">{{ __('Rejected') }}</span>
                                    @endif
                                    @if ($verificationStatus !== 'approved' && \App\Http\Controllers\Dashboard\DriverController::canQuickApprove())
                                        <form action="{{ route('dashboard.drivers.verification.quick-approve', $driver->id) }}" method="POST" class="mt-1" onsubmit="return confirm('{{ __('Verify this account?') }}')">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-success"><i class="ti ti-bolt ti-xs me-1"></i>{{ __('Verify') }}</button>
                                        </form>
                                    @endif
                                </td>
                                <td>
                                    @if ($driver->hasAcceptedCurrentTerms() && $driver->terms_accepted_at)
                                        <span class="badge bg-label-success" title="{{ \Carbon\Carbon::parse($driver->terms_accepted_at)->format('d M Y h:i A') }}">{{ __('Yes') }}</span>
                                    @else
                                        <span class="badge bg-label-danger">{{ __('No') }}</span>
                                    @endif
                                </td>
                                @canany(['delete driver', 'view driver', 'update driver'])
                                    <td>
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill dropdown-toggle hide-arrow" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-popper-config='{"strategy":"fixed"}' aria-label="{{ __('Actions') }}">
                                                <i class="ti ti-dots-vertical ti-md"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @can(['view driver'])
                                                    <a class="dropdown-item" href="{{ route('dashboard.drivers.show', $driver->id) }}"><i class="ti ti-eye me-2"></i>{{ __('View details') }}</a>
                                                    <a class="dropdown-item" href="{{ route('dashboard.drivers.show', $driver->id) }}#documents"><i class="ti ti-files me-2"></i>{{ __('View documents') }}</a>
                                                @endcan
                                                @can(['update driver'])
                                                    <a class="dropdown-item" href="{{ route('dashboard.user.status.update', $driver->id) }}"><i class="ti {{ $driver->is_active == 'active' ? 'ti-user-off' : 'ti-user-check' }} me-2"></i>{{ $driver->is_active == 'active' ? __('Deactivate') : __('Activate') }}</a>
                                                @endcan
                                                @canany(['delete driver'])
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('dashboard.user.destroy', $driver->id) }}" method="POST">
                                                        @method('DELETE')
                                                        @csrf
                                                        <a href="#" class="dropdown-item text-danger delete_confirmation"><i class="ti ti-archive me-2"></i>{{ __('Archive') }}</a>
                                                    </form>
                                                @endcan
                                            </div>
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        //
    </script>
@endsection
