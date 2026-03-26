@extends('layouts.master')

@section('title', __('Restaurants'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Restaurants') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-datatable table-responsive">
                <table class="datatables-users table border-top custom-datatables">
                    <thead>
                        <tr>
                            <th>{{ __('Sr.') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Logo') }}</th>
                            <th>{{ __('Status') }}</th>
                            @canany(['delete restaurant', 'update restaurant', 'view update'])<th>{{ __('Action') }}</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($restaurants as $index => $restaurant)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $restaurant->name }}</td>
                                <td><img src="{{ asset('storage/'.$restaurant->logo) }}" alt="Logo" style="height: 40px;"></td>
                                <td>
                                    <span class="badge me-4 bg-label-{{ $restaurant->is_active == 'active' ? 'success' : 'danger' }}">{{ ucfirst($restaurant->is_active) }}</span>
                                </td>
                                @canany(['delete restaurant', 'update restaurant', 'view update'])
                                    <td class="d-flex">
                                        @canany(['delete restaurant'])
                                            <form action="{{ route('dashboard.restaurants.destroy', $restaurant->id) }}"
                                                method="POST">
                                                @method('DELETE')
                                                @csrf
                                                <a href="#" type="submit"
                                                    class="btn btn-icon btn-text-danger waves-effect waves-light rounded-pill delete-record delete_confirmation"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Delete Restaurant') }}">
                                                    <i class="ti ti-trash ti-md"></i>
                                                </a>
                                            </form>
                                        @endcan
                                        @canany(['update restaurant'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.restaurants.status.update', $restaurant->id) }}"
                                                    class="btn btn-icon btn-text-primary waves-effect waves-light rounded-pill me-1"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ $restaurant->is_active == 'active' ? __('Deactivate Restaurant') : __('Activate Restaurant') }}">
                                                    @if ($restaurant->is_active == 'active')
                                                        <i class="ti ti-toggle-right ti-md text-success"></i>
                                                    @else
                                                        <i class="ti ti-toggle-left ti-md text-danger"></i>
                                                    @endif
                                                </a>
                                            </span>
                                        @endcan
                                        @canany(['view restaurant'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.restaurants.show', $restaurant->id) }}"
                                                    class="btn btn-icon btn-text-warning waves-effect waves-light rounded-pill me-1 edit-order-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('View Restaurant') }}">
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
