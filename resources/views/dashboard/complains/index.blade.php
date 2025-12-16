@extends('layouts.master')

@section('title', __('Complains'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Complains') }}</li>
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
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Submitted At') }}</th>
                            @canany(['delete complain', 'update complain', 'view complain'])<th>{{ __('Action') }}</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($complains as $index => $complain)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $complain->name }}</td>
                                <td>{{ $complain->subject }}</td>
                                <td>
                                    <span class="badge bg-label-{{ $complain->status == 'resolved'
                                            ? 'success'
                                            : ($complain->status == 'pending'
                                                ? 'primary'
                                                : ($complain->status == 'inprogress'
                                                    ? 'warning'
                                                    : 'secondary')) }}">
                                        {{ ucfirst($complain->status) }}
                                    </span>
                                </td>
                                <td>{{ $complain->created_at->format('M d, Y') }}</td>
                                @canany(['delete complain', 'update complain', 'view complain'])
                                    <td class="d-flex">
                                        @canany(['delete complain'])
                                            <form action="{{ route('dashboard.complains.destroy', $complain->id) }}"
                                                method="POST">
                                                @method('DELETE')
                                                @csrf
                                                <a href="#" type="submit"
                                                    class="btn btn-icon btn-text-danger waves-effect waves-light rounded-pill delete-record delete_confirmation"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('Delete Complain') }}">
                                                    <i class="ti ti-trash ti-md"></i>
                                                </a>
                                            </form>
                                        @endcan
                                        @canany(['update complain'])
                                            <span class="text-nowrap">
                                                <a href="javascript:void(0)"
                                                    class="btn btn-icon btn-text-primary waves-effect waves-light rounded-pill me-1 edit-complain-btn"
                                                    data-bs-toggle="modal" data-bs-target="#editComplainModal"
                                                    data-id="{{ $complain->id }}" data-status="{{ $complain->status }}"
                                                    title="{{ __('Edit Complain') }}">
                                                    <i class="ti ti-edit ti-md"></i>
                                                </a>
                                            </span>
                                        @endcan
                                        @can(['view complain'])
                                            <span class="text-nowrap">
                                                <a href="{{ route('dashboard.complains.show', $complain->id) }}"
                                                    class="btn btn-icon btn-text-info waves-effect waves-light rounded-pill me-1 view-order-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ __('View Complain') }}">
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

    <!-- Edit Complain Modal -->
    <div class="modal fade" id="editComplainModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-simple modal-edit-user">
            <div class="modal-content">
                <div class="modal-body">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="text-center mb-6">
                        <h4 class="mb-2">Edit Complain Status</h4>
                        {{-- <p>Updating user details will receive a privacy audit.</p> --}}
                    </div>
                    <form id="editComplainForm" method="POST">
                        @csrf
                        <input type="hidden" name="complain_id" id="complain_id">

                        <div class="mb-3">
                            <label for="status" class="form-label">{{ __('Complain Status') }}</label>
                            <select name="status" id="status" class="form-select select2" required>
                                <option value="pending">{{ __('Pending') }}</option>
                                <option value="inprogress">{{ __('In Progress') }}</option>
                                <option value="resolved">{{ __('Resolved') }}</option>
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
    <script>
        $(document).ready(function() {
            $('.edit-complain-btn').on('click', function() {
                let complainId = $(this).data('id');
                let status = $(this).data('status');

                // Set hidden input
                $('#complain_id').val(complainId);

                // Set dropdown selected value
                $('#status').val(status);

                // Build form action URL using route name
                let actionUrl = "{{ route('dashboard.complains.status.update', ':id') }}";
                actionUrl = actionUrl.replace(':id', complainId);

                $('#editComplainForm').attr('action', actionUrl);
            });
        });
    </script>
@endsection
