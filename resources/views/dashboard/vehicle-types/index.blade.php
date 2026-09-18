@extends('layouts.master')

@section('title', __('Vehicle Types'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Vehicle Types') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Vehicle Types List Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    @canany(['create vehicle type'])
                        <a href="{{ route('dashboard.vehicle-types.create') }}"
                            class="add-new btn btn-primary waves-effect waves-light">
                            <i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span
                                class="d-none d-sm-inline-block">{{ __('Add New Vehicle Type') }}</span>
                        </a>
                    @endcan
                </div>
                @canany(['manage vehicle type icons'])
                    <a href="{{ route('dashboard.vehicle-type-icons.index') }}"
                        class="btn btn-label-secondary waves-effect">
                        <i class="ti ti-photo me-0 me-sm-1 ti-xs"></i><span
                            class="d-none d-sm-inline-block">{{ __('Manage Icons') }}</span>
                    </a>
                @endcan
            </div>
            <div class="card-datatable table-responsive">
                <table class="datatables-users table border-top custom-datatables">
                    <thead>
                        <tr>
                            <th>{{ __('Sr.') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Icon') }}</th>
                            <th>{{ __('Base Fare') }}</th>
                            <th>{{ __('Seats') }}</th>
                            @canany(['delete vehicle type', 'update vehicle type'])<th>{{ __('Action') }}</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vehicleTypes as $index => $vehicleType)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $vehicleType->name }}</td>
                                <td><img height="40px" src="{{ asset($vehicleType->icon) }}" alt=""></td>
                                <td>{{ \App\Helpers\Helper::formatCurrency($vehicleType->base_fare) }}</td>
                                <td>{{ $vehicleType->seats }}</td>
                                @canany(['delete vehicle type', 'update vehicle type'])
                                    <td class="d-flex">
                                        @canany(['delete vehicle type'])
                                            <form action="{{ route('dashboard.vehicle-types.destroy', $vehicleType->id) }}"
                                                method="POST">
                                                @method('DELETE')
                                                @csrf
                                                <a href="#" type="submit"
                                                    class="btn btn-icon btn-text-danger waves-effect waves-light rounded-pill delete-record delete_confirmation"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Delete Vehicle Type') }}">
                                                    <i class="ti ti-trash ti-md"></i>
                                                </a>
                                            </form>
                                        @endcan
                                        @canany(['update vehicle type'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.vehicle-types.edit', $vehicleType->id) }}"
                                                    class="btn btn-icon btn-text-primary waves-effect waves-light rounded-pill me-1 edit-order-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Edit Vehicle Type') }}">
                                                    <i class="ti ti-edit ti-md"></i>
                                                </a>
                                            </span>
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.vehicle-types.status.update', $vehicleType->id) }}"
                                                    class="btn btn-icon btn-text-primary waves-effect waves-light rounded-pill me-1"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ $vehicleType->is_active == 'active' ? __('Deactivate Vehicle Type') : __('Activate Vehicle Type') }}">
                                                    @if ($vehicleType->is_active == 'active')
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
    {{-- <script src="{{asset('assets/js/app-user-list.js')}}"></script> --}}
    <script>
        $(document).ready(function() {
            //
        });
    </script>
@endsection
