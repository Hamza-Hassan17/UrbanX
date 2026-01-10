@extends('layouts.master')

@section('title', __('Boost Hours'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Boost Hours') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Boost Hours List Table -->
        <div class="card">
            <div class="card-header">
                @canany(['create boost hour'])
                    <a href="{{ route('dashboard.boost-hours.create') }}"
                        class="add-new btn btn-primary waves-effect waves-light">
                        <i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span
                            class="d-none d-sm-inline-block">{{ __('Add New Boost Hour') }}</span>
                    </a>
                @endcan
            </div>
            <div class="card-datatable table-responsive">
                <table class="datatables-users table border-top custom-datatables">
                    <thead>
                        <tr>
                            <th>{{ __('Sr.') }}</th>
                            <th>{{ __('Start') }}</th>
                            <th>{{ __('End') }}</th>
                            <th>{{ __('Multiplier') }}</th>
                            @canany(['delete boost hour', 'update boost hour'])<th>{{ __('Action') }}</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($boostHours as $index => $boostHour)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $boostHour->start }}</td>
                                <td>{{ $boostHour->end }}</td>
                                <td>{{ $boostHour->multiplier }}</td>
                                @canany(['delete boost hour', 'update boost hour'])
                                    <td class="d-flex">
                                        @canany(['delete boost hour'])
                                            <form action="{{ route('dashboard.boost-hours.destroy', $boostHour->id) }}"
                                                method="POST">
                                                @method('DELETE')
                                                @csrf
                                                <a href="#" type="submit"
                                                    class="btn btn-icon btn-text-danger waves-effect waves-light rounded-pill delete-record delete_confirmation"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Delete Boost Hour') }}">
                                                    <i class="ti ti-trash ti-md"></i>
                                                </a>
                                            </form>
                                        @endcan
                                        @canany(['update boost hour'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.boost-hours.edit', $boostHour->id) }}"
                                                    class="btn btn-icon btn-text-primary waves-effect waves-light rounded-pill me-1 edit-order-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Edit Boost Hour') }}">
                                                    <i class="ti ti-edit ti-md"></i>
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
