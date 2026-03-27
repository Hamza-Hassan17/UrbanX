@extends('layouts.master')

@section('title', __('Restaurant Vouchers'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Restaurant Vouchers') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header">
                @canany(['create restaurant voucher'])
                    <a href="{{ route('dashboard.restaurant-vouchers.create') }}"
                        class="add-new btn btn-primary waves-effect waves-light">
                        <i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span
                            class="d-none d-sm-inline-block">{{ __('Add Voucher') }}</span>
                    </a>
                @endcan
            </div>
            <div class="card-datatable table-responsive">
                <table class="datatables-users table border-top custom-datatables">
                    <thead>
                        <tr>
                            <th>{{ __('Sr.') }}</th>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Amt/Perc') }}</th>
                            <th>{{ __('Min. Purchase') }}</th>
                            <th>{{ __('Usage limit') }}</th>
                            <th>{{ __('Expires At') }}</th>
                            <th>{{ __('Status') }}</th>
                            @canany(['delete restaurant voucher', 'update restaurant voucher'])<th>{{ __('Action') }}</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vouchers as $index => $voucher)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $voucher->code }}</td>
                                <td>{{ ucfirst($voucher->discount_type) }}</td>
                                <td>
                                    @if ($voucher->discount_type == 'fixed')
                                    {{ \App\Helpers\Helper::formatCurrency($voucher->discount_amount) }}
                                    @else
                                    {{ $voucher->discount_amount.'%' }}
                                    @endif
                                </td>
                                <td>{{ \App\Helpers\Helper::formatCurrency($voucher->minimum_purchase) }}</td>
                                <td>{{ $voucher->per_user_limit }}</td>
                                <td>{{ \Carbon\Carbon::parse($voucher->expires_at)->format('d M Y') }}</td>
                                <td>
                                    <span
                                        class="badge me-4 bg-label-{{ $voucher->is_active == 'active' ? 'success' : 'danger' }}">{{ ucfirst($voucher->is_active) }}</span>
                                </td>
                                @canany(['delete restaurant voucher', 'update restaurant voucher'])
                                    <td class="d-flex">
                                        @canany(['delete restaurant voucher'])
                                            <form action="{{ route('dashboard.restaurant-vouchers.destroy', $voucher->id) }}"
                                                method="POST">
                                                @method('DELETE')
                                                @csrf
                                                <a href="#" type="submit"
                                                    class="btn btn-icon btn-text-danger waves-effect waves-light rounded-pill delete-record delete_confirmation"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Delete Voucher') }}">
                                                    <i class="ti ti-trash ti-md"></i>
                                                </a>
                                            </form>
                                        @endcan
                                        @canany(['update restaurant voucher'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.restaurant-vouchers.edit', $voucher->id) }}"
                                                    class="btn btn-icon btn-text-primary waves-effect waves-light rounded-pill me-1 edit-order-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Edit Voucher') }}">
                                                    <i class="ti ti-edit ti-md"></i>
                                                </a>
                                            </span>
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.restaurant-vouchers.status.update', $voucher->id) }}"
                                                    class="btn btn-icon btn-text-primary waves-effect waves-light rounded-pill me-1"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ $voucher->is_active == 'active' ? __('Deactivate Voucher') : __('Activate Voucher') }}">
                                                    @if ($voucher->is_active == 'active')
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
        $(document).ready(function() {
            //
        });
    </script>
@endsection
