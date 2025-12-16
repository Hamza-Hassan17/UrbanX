@extends('layouts.master')

@section('title', __('Chauffeurs Bookings'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Chauffeurs Bookings') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-datatable table-responsive">
                <table class="datatables-users table border-top custom-datatables">
                    <thead>
                        <tr>
                            <th>{{ __('Sr.') }}</th>
                            <th>{{ __('Booking ID') }}</th>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Vehicle') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Submitted At') }}</th>
                            @canany(['delete chauffeur booking', 'update chauffeur booking'])<th>{{ __('Action') }}</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $index => $booking)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $booking->booking_id }}</td>
                                <td>{{ $booking->user->name }}</td>
                                <td>{{ $booking->vehicle->title }}</td>
                                <td>
                                    <span class="badge bg-label-{{ $booking->status == 'confirmed'
                                            ? 'success'
                                            : ($booking->status == 'completed'
                                                ? 'primary'
                                                : ($booking->status == 'cancelled'
                                                    ? 'danger'
                                                    : 'warning')) }}">
                                        {{ ucfirst($booking->status) }}
                                    </span>
                                </td>
                                <td>{{ $booking->created_at->format('M d, Y') }}</td>
                                @canany(['delete chauffeur booking', 'update chauffeur booking', 'view chauffeur booking'])
                                    <td class="d-flex">
                                        @canany(['delete chauffeur booking'])
                                            <form action="{{ route('dashboard.chauffeur-bookings.destroy', $booking->id) }}"
                                                method="POST">
                                                @method('DELETE')
                                                @csrf
                                                <a href="#" type="submit"
                                                    class="btn btn-icon btn-text-danger waves-effect waves-light rounded-pill delete-record delete_confirmation"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Delete Booking') }}">
                                                    <i class="ti ti-trash ti-md"></i>
                                                </a>
                                            </form>
                                        @endcan
                                        @canany(['update chauffeur booking'])
                                            <span class="text-nowrap">
                                                <a href="javascript:void(0)"
                                                    class="btn btn-icon btn-text-primary waves-effect waves-light rounded-pill me-1 edit-booking-btn"
                                                    data-bs-toggle="modal" data-bs-target="#editBookingModal"
                                                    data-id="{{ $booking->id }}" data-status="{{ $booking->status }}"
                                                    title="{{ __('Edit Booking') }}">
                                                    <i class="ti ti-edit ti-md"></i>
                                                </a>
                                            </span>
                                        @endcan
                                        @can(['view chauffeur booking'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.chauffeur-bookings.show', $booking->id) }}"
                                                    class="btn btn-icon btn-text-info waves-effect waves-light rounded-pill me-1 view-order-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('View Booking') }}">
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

    <!-- Edit Booking Modal -->
    <div class="modal fade" id="editBookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-simple modal-edit-user">
            <div class="modal-content">
                <div class="modal-body">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="text-center mb-6">
                        <h4 class="mb-2">Edit Booking Status</h4>
                        {{-- <p>Updating user details will receive a privacy audit.</p> --}}
                    </div>
                    <form id="editBookingForm" method="POST">
                        @csrf
                        <input type="hidden" name="booking_id" id="booking_id">

                        <div class="mb-3">
                            <label for="status" class="form-label">{{ __('Booking Status') }}</label>
                            <select name="status" id="status" class="form-select select2" required>
                                <option value="pending">{{ __('Pending') }}</option>
                                <option value="confirmed">{{ __('Confirmed') }}</option>
                                <option value="completed">{{ __('Completed') }}</option>
                                <option value="cancelled">{{ __('Cancelled') }}</option>
                            </select>
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary me-3">Submit</button>
                            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal"
                                aria-label="Close">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!--/ Edit Order Modal -->
@endsection

@section('script')
    {{-- <script src="{{asset('assets/js/app-user-list.js')}}"></script> --}}
    <script>
        $(document).ready(function() {
            $('.edit-booking-btn').on('click', function() {
                let bookingId = $(this).data('id');
                let status = $(this).data('status');

                // Set hidden input
                $('#booking_id').val(bookingId);

                // Set dropdown selected value
                $('#status').val(status);

                // Build form action URL using route name
                let actionUrl = "{{ route('dashboard.chauffeur-bookings.status.update', ':id') }}";
                actionUrl = actionUrl.replace(':id', bookingId);

                $('#editBookingForm').attr('action', actionUrl);
            });
        });
    </script>
@endsection
