@extends('layouts.master')

@section('title', __('Finance'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Finance') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        @include('dashboard.finance.partials.tabs')

        <div class="card mb-6">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Restaurant Payable Report') }}</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('dashboard.finance.restaurant-payable') }}" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Start Date') }}</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('End Date') }}</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Restaurant') }}</label>
                        <select name="restaurant_id" class="form-select">
                            <option value="">{{ __('All Restaurants') }}</option>
                            @foreach ($restaurants as $restaurant)
                                <option value="{{ $restaurant->id }}" {{ request('restaurant_id') == $restaurant->id ? 'selected' : '' }}>{{ $restaurant->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">{{ __('Generate') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($report)
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ $report['start_date'] }} {{ __('to') }} {{ $report['end_date'] }}</h5>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Restaurant') }}</th>
                                <th>{{ __('Orders') }}</th>
                                <th>{{ __('Sales (Full Price)') }}</th>
                                <th>{{ __('Restaurant-Funded Discounts') }}</th>
                                <th>{{ __('Commission') }}</th>
                                <th>{{ __('Payable') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['rows'] as $row)
                                <tr>
                                    <td>{{ $row['restaurant_name'] }}</td>
                                    <td>{{ $row['order_count'] }}</td>
                                    <td>{{ \App\Helpers\Helper::formatCurrency($row['sales_full_price']) }}</td>
                                    <td class="text-danger">({{ \App\Helpers\Helper::formatCurrency($row['restaurant_funded_discounts']) }})</td>
                                    <td class="text-danger">({{ \App\Helpers\Helper::formatCurrency($row['commission']) }})</td>
                                    <td><strong>{{ \App\Helpers\Helper::formatCurrency($row['payable_amount']) }}</strong></td>
                                    <td>
                                        @if ($row['paid'])
                                            <span class="badge bg-label-success">{{ __('Paid') }}</span>
                                        @else
                                            <span class="badge bg-label-warning">{{ __('Unpaid') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (!$row['paid'])
                                            <button type="button" class="btn btn-sm btn-success mark-restaurant-paid-btn"
                                                data-bs-toggle="modal" data-bs-target="#markRestaurantPaidModal"
                                                data-restaurant-id="{{ $row['restaurant_id'] }}"
                                                data-restaurant-name="{{ $row['restaurant_name'] }}"
                                                data-sales="{{ $row['sales_full_price'] }}"
                                                data-discounts="{{ $row['restaurant_funded_discounts'] }}"
                                                data-commission="{{ $row['commission'] }}"
                                                data-payable="{{ $row['payable_amount'] }}">
                                                {{ __('Mark as Paid') }}
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">{{ __('No completed orders found for this selection.') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>{{ __('Grand Total') }}</th>
                                <th></th>
                                <th>{{ \App\Helpers\Helper::formatCurrency($report['grand_total_sales']) }}</th>
                                <th>({{ \App\Helpers\Helper::formatCurrency($report['grand_total_discounts']) }})</th>
                                <th>({{ \App\Helpers\Helper::formatCurrency($report['grand_total_commission']) }})</th>
                                <th>{{ \App\Helpers\Helper::formatCurrency($report['grand_total_payable']) }}</th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        {{-- Batch 1 Part 9 -- Mark as Paid modal --}}
        <div class="modal fade" id="markRestaurantPaidModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('dashboard.finance.restaurant-payable.mark-paid') }}">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Record Payout') }} -- <span id="markRestaurantPaidName"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="restaurant_id" id="markRestaurantPaidId">
                            <input type="hidden" name="period_start" value="{{ request('start_date') }}">
                            <input type="hidden" name="period_end" value="{{ request('end_date') }}">
                            <input type="hidden" name="sales_full_price" id="markRestaurantPaidSales">
                            <input type="hidden" name="restaurant_funded_discounts" id="markRestaurantPaidDiscounts">
                            <input type="hidden" name="commission" id="markRestaurantPaidCommission">
                            <input type="hidden" name="payable_amount" id="markRestaurantPaidPayable">
                            <p>{{ __('Payable amount') }}: <strong id="markRestaurantPaidPayableDisplay"></strong></p>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Method') }}</label>
                                <select name="method" class="form-select" required>
                                    <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                                    <option value="cash">{{ __('Cash') }}</option>
                                    <option value="easypaisa">{{ __('Easypaisa') }}</option>
                                    <option value="jazzcash">{{ __('JazzCash') }}</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Reference') }}</label>
                                <input type="text" name="reference" class="form-control">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-success">{{ __('Record Payout') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.mark-restaurant-paid-btn');
            if (!btn) return;
            document.getElementById('markRestaurantPaidId').value = btn.dataset.restaurantId;
            document.getElementById('markRestaurantPaidName').textContent = btn.dataset.restaurantName;
            document.getElementById('markRestaurantPaidSales').value = btn.dataset.sales;
            document.getElementById('markRestaurantPaidDiscounts').value = btn.dataset.discounts;
            document.getElementById('markRestaurantPaidCommission').value = btn.dataset.commission;
            document.getElementById('markRestaurantPaidPayable').value = btn.dataset.payable;
            document.getElementById('markRestaurantPaidPayableDisplay').textContent = btn.dataset.payable;
        });
    </script>
@endsection
