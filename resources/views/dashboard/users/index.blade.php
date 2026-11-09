@extends('layouts.master')

@section('title', __('Users'))

@section('css')
    <style>
        .edit-loader {
            width: 100%;
        }

        .edit-loader .sk-chase {
            display: block;
            margin: 0 auto;
        }

        .modal-card{
            background: transparent !important; 
            border: none !important; 
            box-shadow: none !important;
        }
    </style>
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Users') }}</li>
@endsection
{{-- @dd($totalArchivedUsers) --}}
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="row g-6 mb-6">
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="content-left">
                                <span class="text-heading">{{ __('Users') }}</span>
                                <div class="d-flex align-items-center my-1">
                                    <h4 class="mb-0 me-2">{{ $totalUsers }}</h4>
                                </div>
                                <small class="mb-0">{{ __('Total Users') }}</small>
                            </div>
                            <div class="avatar">
                                <span class="avatar-initial rounded bg-label-primary">
                                    <i class="ti ti-users ti-26px"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="content-left">
                                <span class="text-heading">{{ __('Deactivated Users') }}</span>
                                <div class="d-flex align-items-center my-1">
                                    <h4 class="mb-0 me-2">
                                        {{ $totalDeactivatedUsers }}
                                    </h4>
                                </div>
                                <small class="mb-0">{{ __('Total Deactive Users') }} </small>
                            </div>
                            <div class="avatar">
                                <span class="avatar-initial rounded bg-label-danger">
                                    <i class="ti ti-user-off ti-26px"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="content-left">
                                <span class="text-heading">{{ __('Active Users') }}</span>
                                <div class="d-flex align-items-center my-1">
                                    <h4 class="mb-0 me-2">{{ $totalActiveUsers }}</h4>
                                </div>
                                <small class="mb-0">{{ __('Total Active Users') }}</small>
                            </div>
                            <div class="avatar">
                                <span class="avatar-initial rounded bg-label-success">
                                    <i class="ti ti-user-check ti-26px"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="content-left">
                                <span class="text-heading">{{ __('Archived Users') }}</span>
                                <div class="d-flex align-items-center my-1">
                                    <h4 class="mb-0 me-2">{{ $totalArchivedUsers }}</h4>
                                </div>
                                <small class="mb-0">{{ __('Total Archived Users') }}</small>
                            </div>
                            <div class="avatar">
                                <span class="avatar-initial rounded bg-label-warning">
                                    <i class="ti ti-archive ti-26px"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Users List Table -->
        <div class="card">
            <div class="card-header">
                @canany(['create user'])
                    <button class="add-new btn btn-primary waves-effect waves-light" data-bs-toggle="offcanvas"
                        data-bs-target="#offcanvasAddUser">
                        <i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">{{ __('Add New User') }}</span>
                    </button>
                @endcan
            </div>
            <div class="card-datatable table-responsive">
                <table class="datatables-users table border-top custom-datatables">
                    <thead>
                        <tr>
                            <th>{{ __('Sr.') }}</th>
                            <th>{{ __('Customer') }}</th>
                            <th>{{ __('Created Date') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Terms Accepted') }}</th>
                            @canany(['delete user', 'update user', 'view user'])<th>{{ __('Action') }}</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $index => $user)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>@include('dashboard.partials.person-cell', ['name' => $user->name, 'email' => $user->email, 'url' => auth()->user()->can('view user') ? route('dashboard.customers.show', $user->id) : null])</td>
                                <td class="text-nowrap">{{ $user->created_at->format('M d, Y') }}</td>
                                <td>
                                    <span
                                        class="badge me-4 bg-label-{{ $user->is_active == 'active' ? 'success' : 'danger' }}">{{ ucfirst($user->is_active) }}</span>
                                </td>
                                <td>
                                    @if ($user->hasAcceptedCurrentTerms() && $user->terms_accepted_at)
                                        <span class="badge bg-label-success" title="{{ \Carbon\Carbon::parse($user->terms_accepted_at)->format('d M Y h:i A') }}">{{ __('Yes') }}</span>
                                    @else
                                        <span class="badge bg-label-danger">{{ __('No') }}</span>
                                    @endif
                                </td>
                                @canany(['delete user', 'update user', 'view user'])
                                    <td>
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill dropdown-toggle hide-arrow" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-popper-config='{"strategy":"fixed"}' aria-label="{{ __('Actions') }}">
                                                <i class="ti ti-dots-vertical ti-md"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @can(['view user'])
                                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalCenter" data-user-id="{{ $user->id }}"><i class="ti ti-eye me-2"></i>{{ __('View details') }}</button>
                                                @endcan
                                                @canany(['update user'])
                                                    <button type="button" class="dropdown-item" data-bs-toggle="offcanvas" data-bs-target="#offcanvasEditUser" data-user-id="{{ $user->id }}"><i class="ti ti-edit me-2"></i>{{ __('Edit') }}</button>
                                                    <a class="dropdown-item" href="{{ route('dashboard.user.status.update', $user->id) }}"><i class="ti {{ $user->is_active == 'active' ? 'ti-user-off' : 'ti-user-check' }} me-2"></i>{{ $user->is_active == 'active' ? __('Deactivate') : __('Activate') }}</a>
                                                @endcan
                                                @canany(['delete user'])
                                                    @if (!($user->getRoleNames()->first() == 'admin' || $user->getRoleNames()->first() == 'super-admin'))
                                                        <div class="dropdown-divider"></div>
                                                        <form action="{{ route('dashboard.user.destroy', $user->id) }}" method="POST">
                                                            @method('DELETE')
                                                            @csrf
                                                            <a href="#" class="dropdown-item text-danger delete_confirmation"><i class="ti ti-archive me-2"></i>{{ __('Archive') }}</a>
                                                        </form>
                                                    @endif
                                                @endcan
                                            </div>
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <!-- Offcanvas to add new user -->
            @include('dashboard.users.sections.add-offcanvas')
            <!-- Offcanvas to edit user -->
            @include('dashboard.users.sections.edit-offcanvas')
        </div>
    </div>
    <!-- Modal to view user details -->
    @can(['view user'])
        @include('dashboard.users.sections.view-modal')
    @endcan
@endsection

@section('script')
    {{-- <script src="{{asset('assets/js/app-user-list.js')}}"></script> --}}
    <script>
        $(document).ready(function() {
            $('.edit-loader').hide();
            $('#editUserForm').show();
            // Event listener for edit modal opening
            $('#offcanvasEditUser').on('show.bs.offcanvas', function(event) {
                $('.edit-loader').show();
                $('#editUserForm').hide();
                var button = $(event.relatedTarget);
                var userId = button.data('user-id');
                fetchUserData(userId);
            });
            $('#modalCenter').on('show.bs.modal', function(event) {
                $('.edit-loader').show();
                $('#user-info').hide();
                var button = $(event.relatedTarget);
                var userId = button.data('user-id');
                fetchUserData(userId);
            });
            var editUserRoute = "{{ route('dashboard.user.edit', ':userId') }}";
            var updateUserRoute = "{{ route('dashboard.user.update', ':userId') }}";

            function fetchUserData(userId) {
                var url = editUserRoute.replace(':userId', userId);
                $.ajax({
                    url: url, // Adjust API URL as necessary
                    type: 'GET',
                    success: function(data) {
                        if (data.success) {
                            var user = data.user;
                            // Check if it's the edit offcanvas or the view modal
                            var isEdit = $('#offcanvasEditUser').hasClass('show');
                            var isModal = $('#modalCenter').hasClass('show');
                            if (isEdit) {
                                $('#edit_first_name').val(user.first_name);
                                $('#edit_last_name').val(user.last_name);
                                $('#edit_email').val(user.email);
                                $('#edit-user-role').val(user.role).trigger('change');

                                $('.edit-loader').hide();
                                $('#editUserForm').show();
                                // ✅ Set form action dynamically using the route variable
                                var updateUrl = updateUserRoute.replace(':userId', user.id);
                                $('#editUserForm').attr('action', updateUrl);
                            }
                            if (isModal) {
                                // ✅ Update Modal User Info
                                var profileImage = user.profile_image 
                                    ? '{{ asset("") }}' + user.profile_image 
                                    : '{{ asset("assets/img/default/user.png") }}';
                                $('#user-info img').attr('src', profileImage);
                                $('#user-info .user-info h5').text(user.full_name ? user.full_name :
                                    'N/A');
                                $('#user-info .user-info span.badge').text(user.role ? user.role.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) :
                                    'N/A');

                                var userDetails = `
                                    <li class="mb-2"><span class="h6">{{ __('Username') }}:</span> <span>${user.username ? user.username : 'N/A'}</span></li>
                                    <li class="mb-2"><span class="h6">{{ __('Email') }}:</span> <span>${user.email ? user.email : 'N/A'}</span></li>
                                    <li class="mb-2"><span class="h6">{{ __('Status') }}:</span> <span>${user.is_active ? user.is_active.replace(/\b\w/g, c => c.toUpperCase()) : 'Inactive'}</span></li>
                                    <li class="mb-2"><span class="h6">{{ __('Designation') }}:</span> <span>${user.designation ? user.designation : 'N/A'}</span></li>
                                    <li class="mb-2"><span class="h6">{{ __('Contact') }}:</span> <span>${user.phone_number ? user.phone_number : 'N/A'}</span></li>
                                    <li class="mb-2"><span class="h6">{{ __('Language') }}:</span> <span>${user.language ? user.language : 'N/A'}</span></li>
                                    <li class="mb-2"><span class="h6">{{ __('Country') }}:</span> <span>${user.country ? user.country : 'N/A'}</span></li>
                                    `;
                                $('#user-info .info-container ul').html(userDetails);

                                // Update Social Media Links
                                var socialLinks = '';
                                if (user.facebook_url) {
                                    socialLinks += `<a href="${user.facebook_url}" target="_blank" style="color: inherit;"><i class="fab fa-facebook fa-lg"></i></a>`;
                                }
                                if (user.linkedin_url) {
                                    socialLinks += `<a href="${user.linkedin_url}" target="_blank" style="color: inherit;"><i class="fab fa-linkedin fa-lg"></i></a>`;
                                }
                                if (user.skype_url) {
                                    socialLinks += `<a href="${user.skype_url}" target="_blank" style="color: inherit;"><i class="fab fa-skype fa-lg"></i></a>`;
                                }
                                if (user.instagram_url) {
                                    socialLinks += `<a href="${user.instagram_url}" target="_blank" style="color: inherit;"><i class="fab fa-instagram fa-lg"></i></a>`;
                                }
                                if (user.github_url) {
                                    socialLinks += `<a href="${user.github_url}" target="_blank" style="color: inherit;"><i class="fab fa-github fa-lg"></i></a>`;
                                }
                                
                                $('#modalSocialIcons').html(socialLinks); // Update social icons container

                                $('.edit-loader').hide();
                                $('#user-info').show();
                            }

                        }
                    },
                    error: function(xhr, status, error) {
                        $('.edit-loader').hide();
                        $('#editUserForm').show();
                        $('#user-info').show();
                        console.error('Error fetching user data:', error);
                    }
                });
            }

            $("#addNewUserForm").on("submit", function() {
                $("#addUserBtn").prop("disabled", true); // Disable button
                $("#addUserLoader").removeClass("d-none"); // Show spinner
            });

            $("#editUserForm").on("submit", function() {
                $("#editUserBtn").prop("disabled", true); // Disable button
                $("#editUserLoader").removeClass("d-none"); // Show spinner
            });
        });
    </script>
@endsection
