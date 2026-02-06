@extends('layouts.master')

@section('title', __('Rides'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Rides') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Rides List Table -->
        <div class="card">
            <div class="card-datatable table-responsive">
                <table class="datatables-users table border-top custom-datatables">
                    <thead>
                        <tr>
                            <th>{{ __('Sr.') }}</th>
                            <th>{{ __('Passenger') }}</th>
                            <th>{{ __('Driver') }}</th>
                            <th>{{ __('Distance') }}</th>
                            <th>{{ __('Total') }}</th>
                            <th>{{ __('Status') }}</th>
                            @canany(['delete ride', 'update ride', 'view ride'])<th>{{ __('Action') }}</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rides as $index => $ride)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $ride->passenger ? $ride->passenger->name : 'N/A' }}</td>
                                <td>{{ $ride->driver ? $ride->driver->name : 'N/A' }}</td>
                                <td>{{ $ride->distance_km.' km' }}</td>
                                <td>{{ \App\Helpers\Helper::formatCurrency($ride->total_fare) }}</td>
                                @php
                                    $statusColors = [
                                        'requested' => 'bg-label-warning',
                                        'accepted'  => 'bg-label-info',
                                        'en_route'  => 'bg-label-primary',
                                        'arrived'   => 'bg-label-secondary',
                                        'started'   => 'bg-label-dark',
                                        'completed' => 'bg-label-success',
                                        'cancelled' => 'bg-label-danger',
                                    ];
                                @endphp

                                <td>
                                    <span class="badge me-4 {{ $statusColors[$ride->status] ?? 'bg-label-secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $ride->status)) }}
                                    </span>
                                </td>
                                @canany(['delete ride', 'update ride'])
                                    <td class="d-flex">
                                        @canany(['delete ride'])
                                            <form action="{{ route('dashboard.rides.destroy', $ride->id) }}"
                                                method="POST">
                                                @method('DELETE')
                                                @csrf
                                                <a href="#" type="submit"
                                                    class="btn btn-icon btn-text-danger waves-effect waves-light rounded-pill delete-record delete_confirmation"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Delete Ride') }}">
                                                    <i class="ti ti-trash ti-md"></i>
                                                </a>
                                            </form>
                                        @endcan
                                        @canany(['update ride'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.rides.edit', $ride->id) }}"
                                                    class="btn btn-icon btn-text-primary waves-effect waves-light rounded-pill me-1 edit-order-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Edit Ride') }}">
                                                    <i class="ti ti-edit ti-md"></i>
                                                </a>
                                            </span>
                                        @endcan
                                        @canany(['view ride'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.rides.show', $ride->id) }}"
                                                    class="btn btn-icon btn-text-warning waves-effect waves-light rounded-pill me-1 edit-order-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('View Ride Details') }}">
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
