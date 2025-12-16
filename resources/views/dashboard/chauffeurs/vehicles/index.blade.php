@extends('layouts.master')

@section('title', __('Chauffeurs Vehicles'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Chauffeurs Vehicles') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Chauffeurs Vehicles List Table -->
        <div class="card">
            <div class="card-header">
                @canany(['create chauffeur vehicle'])
                    <a href="{{ route('dashboard.chauffeur-vehicles.create') }}"
                        class="add-new btn btn-primary waves-effect waves-light">
                        <i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span
                            class="d-none d-sm-inline-block">{{ __('Add New Vehicle') }}</span>
                    </a>
                @endcan
            </div>
            <div class="card-datatable table-responsive">
                <table class="datatables-users table border-top custom-datatables">
                    <thead>
                        <tr>
                            <th>{{ __('Sr.') }}</th>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Brand') }}</th>
                            <th>{{ __('Model') }}</th>
                            @canany(['delete chauffeur vehicle', 'update chauffeur vehicle'])<th>{{ __('Action') }}</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vehicles as $index => $vehicle)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $vehicle->title }}</td>
                                <td>{{ $vehicle->carBrand->name }}</td>
                                <td>{{ $vehicle->car_model }}</td>
                                @canany(['delete chauffeur vehicle', 'update chauffeur vehicle', 'view chauffeur vehicle'])
                                    <td class="d-flex">
                                        @canany(['delete chauffeur vehicle'])
                                            <form action="{{ route('dashboard.chauffeur-vehicles.destroy', $vehicle->id) }}"
                                                method="POST">
                                                @method('DELETE')
                                                @csrf
                                                <a href="#" type="submit"
                                                    class="btn btn-icon btn-text-danger waves-effect waves-light rounded-pill delete-record delete_confirmation"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Delete Vehicle') }}">
                                                    <i class="ti ti-trash ti-md"></i>
                                                </a>
                                            </form>
                                        @endcan
                                        @canany(['update chauffeur vehicle'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.chauffeur-vehicles.edit', $vehicle->id) }}"
                                                    class="btn btn-icon btn-text-primary waves-effect waves-light rounded-pill me-1 edit-order-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Edit Vehicle') }}">
                                                    <i class="ti ti-edit ti-md"></i>
                                                </a>
                                            </span>
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.chauffeur-vehicles.status.update', $vehicle->id) }}"
                                                    class="btn btn-icon btn-text-primary waves-effect waves-light rounded-pill me-1"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ $vehicle->is_active == 'active' ? __('Deactivate Vehicle') : __('Activate Vehicle') }}">
                                                    @if ($vehicle->is_active == 'active')
                                                        <i class="ti ti-toggle-right ti-md text-success"></i>
                                                    @else
                                                        <i class="ti ti-toggle-left ti-md text-danger"></i>
                                                    @endif
                                                </a>
                                            </span>
                                        @endcan
                                        @can(['view chauffeur vehicle'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.chauffeur-vehicles.show', $vehicle->id) }}"
                                                    class="btn btn-icon btn-text-info waves-effect waves-light rounded-pill me-1 view-order-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('View Vehicle') }}">
                                                    <i class="ti ti-eye ti-md"></i>
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
