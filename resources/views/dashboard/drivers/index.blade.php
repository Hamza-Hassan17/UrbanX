@extends('layouts.master')

@section('title', __('Drivers'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Drivers') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Users List Table -->
        <div class="card">
            <div class="card-datatable table-responsive">
                <table class="datatables-users table border-top custom-datatables">
                    <thead>
                        <tr>
                            <th>{{ __('Sr.') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Phone Number') }}</th>
                            <th>{{ __('City') }}</th>
                            <th>{{ __('Created Date') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Verification') }}</th>
                            @canany(['delete driver', 'view driver', 'update driver'])<th>{{ __('Action') }}</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($drivers as $index => $driver)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $driver->name }}</td>
                                <td>{{ $driver->email }}</td>
                                <td>{{ $driver->profile->phone_number }}</td>
                                <td>
                                    @can(['update driver'])
                                        <form action="{{ route('dashboard.drivers.update', $driver->id) }}" method="POST" class="d-flex gap-1">
                                            @csrf
                                            @method('PUT')
                                            <input type="text" name="city" class="form-control form-control-sm" style="width: 110px;"
                                                value="{{ $driver->profile->city ?? '' }}" placeholder="Unassigned">
                                            <button type="submit" class="btn btn-icon btn-sm btn-text-primary" title="Save City">
                                                <i class="ti ti-check ti-sm"></i>
                                            </button>
                                        </form>
                                    @else
                                        {{ $driver->profile->city ?? 'Unassigned' }}
                                    @endcan
                                </td>
                                <td>{{ $driver->created_at->format('M d, Y') }}</td>
                                <td>
                                    <span
                                        class="badge me-4 bg-label-{{ $driver->is_active == 'active' ? 'success' : 'danger' }}">{{ ucfirst($driver->is_active) }}</span>
                                </td>
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
                                </td>
                                @canany(['delete driver', 'view driver', 'update driver'])
                                    <td class="d-flex">
                                        @canany(['delete driver'])
                                            <form action="{{ route('dashboard.user.destroy', $driver->id) }}" method="POST">
                                                @method('DELETE')
                                                @csrf
                                                <a href="#" type="submit"
                                                    class="btn btn-icon btn-text-danger waves-effect waves-light rounded-pill delete-record delete_confirmation"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Archive Driver') }}">
                                                    <i class="ti ti-trash ti-md"></i>
                                                </a>
                                            </form>
                                        @endcan
                                        @can(['view driver'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.drivers.show', $driver->id) }}"
                                                    class="btn btn-icon btn-text-warning waves-effect waves-light rounded-pill me-1 edit-order-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('View Driver Details') }}">
                                                    <i class="ti ti-eye ti-md"></i>
                                                </a>
                                            </span>
                                        @endcan
                                        @can(['update driver'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.user.status.update', $driver->id) }}"
                                                    class="btn btn-icon btn-text-primary waves-effect waves-light rounded-pill me-1"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ $driver->is_active == 'active' ? __('Deactivate User') : __('Activate User') }}">
                                                    @if ($driver->is_active == 'active')
                                                        <i class="ti ti-toggle-right ti-md text-success"></i>
                                                    @else
                                                        <i class="ti ti-toggle-left ti-md text-danger"></i>
                                                    @endif
                                                </a>
                                            </span>
                                        @endcan
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
