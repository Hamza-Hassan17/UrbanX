@extends('layouts.master')

@section('title', __('Drivers Details'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.drivers.index') }}">{{ __('Drivers') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Details') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="row">
            <!-- User Sidebar -->
            <div class="col-xl-4 col-lg-5 order-1 order-md-0">
                <!-- User Card -->
                <div class="card mb-6">
                    <div class="card-body pt-12">
                        <div class="user-avatar-section">
                            <div class="d-flex align-items-center flex-column">
                                <img class="img-fluid rounded mb-4"
                                    src="{{ asset($driver->profile->profile_image ?? 'assets/img/default/user.png') }}"
                                    height="120" width="120" alt="User avatar" />
                                <div class="user-info text-center">
                                    <h5>{{ $driver->name }}</h5>
                                    <span class="badge bg-label-secondary">Driver</span>
                                    @php $verification = $driver->driverVerification; @endphp
                                    @if (!$verification || $verification->status === 'not_submitted')
                                        <span class="badge bg-label-secondary">Not Submitted</span>
                                    @elseif ($verification->status === 'submitted')
                                        <span class="badge bg-label-warning">Pending Review</span>
                                    @elseif ($verification->status === 'approved')
                                        <span class="badge bg-label-success">Verified</span>
                                    @elseif ($verification->status === 'rejected')
                                        <span class="badge bg-label-danger">Rejected</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <h5 class="pb-4 border-bottom mb-4 mt-4">Personal Details</h5>
                        <div class="info-container">
                            <ul class="list-unstyled mb-6">
                                <li class="mb-2">
                                    <span class="h6">Username:</span>
                                    <span>{{ '@' . $driver->username }}</span>
                                </li>
                                <li class="mb-2">
                                    <span class="h6">Email:</span>
                                    <span>{{ $driver->email }}</span>
                                </li>
                                <li class="mb-2">
                                    <span class="h6">Contact:</span>
                                    <span>{{ $driver->profile->phone_number ?? 'N/A' }}</span>
                                </li>
                                <li class="mb-2">
                                    <span class="h6">Date of Birth:</span>
                                    <span>{{ $driver->profile->dob ? \Carbon\Carbon::parse($driver->profile->dob)->format('d M, Y') : 'N/A' }}</span>
                                </li>
                                <li class="mb-2">
                                    <span class="h6">Gender:</span>
                                    <span>{{ $driver->profile->gender ?? 'N/A' }}</span>
                                </li>
                                <li class="mb-2">
                                    <span class="h6">City:</span>
                                    <span>{{ $driver->profile->city ?? 'Unassigned' }}</span>
                                </li>
                            </ul>
                        </div>
                        {{-- City is admin-assigned once at onboarding, not derived from
                             GPS -- used to scope the multi-city live tracking view. --}}
                        @can(['update driver'])
                            <form action="{{ route('dashboard.drivers.update', $driver->id) }}" method="POST" class="d-flex gap-2">
                                @csrf
                                @method('PUT')
                                <input type="text" name="city" class="form-control form-control-sm"
                                    value="{{ $driver->profile->city ?? '' }}" placeholder="e.g. Karachi">
                                <button type="submit" class="btn btn-sm btn-primary text-nowrap">Save City</button>
                            </form>
                        @endcan
                    </div>
                </div>
                <!-- /User Card -->
            </div>
            <!--/ User Sidebar -->

            <!-- User Content -->
            <div class="col-xl-8 col-lg-7 order-0 order-md-1">
                <div class="card mb-6">
                    <h5 class="card-header">Driver Details</h5>
                    <div class="card-body pt-1">

                        {{-- Vehicle Details --}}
                        @if ($driver->driverVehicle)
                            <h6 class="fw-bold mt-3">Vehicle Information</h6>
                            <table class="table table-bordered table-sm">
                                <tbody>
                                    <tr>
                                        <th>Vehicle Type</th>
                                        <td>{{ $driver->driverVehicle->vehicleType->name }}</td>
                                    </tr>
                                    <tr>
                                        <th>Vehicle Name</th>
                                        <td>{{ $driver->driverVehicle->vehicle_name }}</td>
                                    </tr>
                                    <tr>
                                        <th>Make</th>
                                        <td>{{ $driver->driverVehicle->vehicle_make ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Model</th>
                                        <td>{{ $driver->driverVehicle->vehicle_model ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Color</th>
                                        <td>{{ $driver->driverVehicle->vehicle_color ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Year</th>
                                        <td>{{ $driver->driverVehicle->vehicle_year ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Plate Number</th>
                                        <td>{{ $driver->driverVehicle->vehicle_plate_number ?? '—' }}</td>
                                    </tr>
                                    @if ($driver->driverVehicle->vehicle_images && json_decode($driver->driverVehicle->vehicle_images, true))
                                        <tr>
                                            <th>Images</th>
                                            <td>
                                                @foreach (json_decode($driver->driverVehicle->vehicle_images, true) as $image)
                                                    <img src="{{ asset('storage/' . $image) }}" alt="Vehicle Image"
                                                        class="rounded border me-2 mb-2" width="100">
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endif
                                    @if ($driver->driverVehicle->registration_paper)
                                        <tr>
                                            <th>Registration Paper</th>
                                            <td>
                                                <img src="{{ asset('storage/' . $driver->driverVehicle->registration_paper) }}"
                                                    alt="Registration Paper" class="rounded border" width="100">
                                            </td>
                                        </tr>
                                    @endif
                                    @if ($driver->driverVehicle->vehicle_video)
                                        <tr>
                                            <th>Vehicle Video</th>
                                            <td>
                                                <video controls width="240" class="rounded border">
                                                    <source src="{{ asset('storage/' . $driver->driverVehicle->vehicle_video) }}">
                                                </video>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        @else
                            <p class="text-muted">No vehicle details available.</p>
                        @endif

                        <hr>

                        {{-- License Details --}}
                        @if ($driver->driverLicense)
                            <h6 class="fw-bold mt-3">License Information</h6>
                            <table class="table table-bordered table-sm">
                                <tbody>
                                    <tr>
                                        <th>Name</th>
                                        <td>{{ $driver->driverLicense->name }}</td>
                                    </tr>
                                    <tr>
                                        <th>License Number</th>
                                        <td>{{ $driver->driverLicense->license_number }}</td>
                                    </tr>
                                    <tr>
                                        <th>Address</th>
                                        <td>{{ $driver->driverLicense->address }}</td>
                                    </tr>
                                    <tr>
                                        <th>License Images</th>
                                        <td>
                                            <img src="{{ asset('storage/' . $driver->driverLicense->front_picture) }}"
                                                width="100" class="me-2 rounded border" alt="Front">
                                            <img src="{{ asset('storage/' . $driver->driverLicense->back_picture) }}"
                                                width="100" class="rounded border" alt="Back">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        @else
                            <p class="text-muted">No license details available.</p>
                        @endif

                        <hr>

                        {{-- CNIC Details --}}
                        @if ($driver->driverCnic)
                            <h6 class="fw-bold mt-3">CNIC Information</h6>
                            <table class="table table-bordered table-sm">
                                <tbody>
                                    <tr>
                                        <th>Name</th>
                                        <td>{{ $driver->driverCnic->name }}</td>
                                    </tr>
                                    <tr>
                                        <th>CNIC Number</th>
                                        <td>{{ $driver->driverCnic->cnic_number }}</td>
                                    </tr>
                                    <tr>
                                        <th>Issue Date</th>
                                        <td>{{ \Carbon\Carbon::parse($driver->driverCnic->issue_date)->format('d M, Y') }}</td>
                                    </tr>
                                    <tr>
                                        <th>CNIC Images</th>
                                        <td>
                                            <img src="{{ asset('storage/' . $driver->driverCnic->front_picture) }}"
                                                width="100" class="me-2 rounded border" alt="Front">
                                            <img src="{{ asset('storage/' . $driver->driverCnic->back_picture) }}"
                                                width="100" class="rounded border" alt="Back">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        @else
                            <p class="text-muted">No CNIC details available.</p>
                        @endif

                        <hr>

                        {{-- Selfie --}}
                        <h6 class="fw-bold mt-3">Selfie</h6>
                        @if ($driver->driverSelfie)
                            <img src="{{ asset('storage/' . $driver->driverSelfie->picture) }}" alt="Selfie"
                                class="rounded border" width="100">
                        @else
                            <p class="text-muted">No selfie submitted.</p>
                        @endif

                    </div>
                </div>

                {{-- Verification Review --}}
                @can(['update driver'])
                    <div class="card mb-6">
                        <h5 class="card-header">Verification Review</h5>
                        <div class="card-body pt-1">
                            @if ($verification && $verification->status === 'rejected' && $verification->rejection_reason)
                                <div class="alert alert-danger">
                                    <strong>Last rejection reason:</strong> {{ $verification->rejection_reason }}
                                </div>
                            @endif
                            <div class="d-flex gap-2">
                                <form action="{{ route('dashboard.drivers.verification.approve', $driver->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-success"
                                        {{ !$verification || $verification->status !== 'submitted' ? 'disabled' : '' }}>
                                        Approve
                                    </button>
                                </form>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                                    data-bs-target="#rejectVerificationModal"
                                    {{ !$verification || $verification->status !== 'submitted' ? 'disabled' : '' }}>
                                    Reject / Request Resubmission
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="rejectVerificationModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('dashboard.drivers.verification.reject', $driver->id) }}" method="POST">
                                @csrf
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Reject Verification</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <label class="form-label">Reason (shown to the driver)</label>
                                        <textarea name="rejection_reason" class="form-control" rows="3" required
                                            placeholder="e.g. Vehicle registration paper is unclear, please resubmit."></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger">Reject</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endcan
            </div>
            <!--/ User Content -->
        </div>
    </div>
@endsection

@section('script')
    <script>
        //
    </script>
@endsection
