@extends('layouts.master')

@section('title', __('Send Notification'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Send Notification') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-6">
            <!-- Account -->
            <div class="card-body pt-4">
                <form method="POST" action="{{ route('dashboard.notifications.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row p-5">
                        <h3>{{ __('Send New Notification') }}</h3>

                        <div class="mb-4 col-md-12">
                            <label class="form-label d-block">{{ __('Send To') }}</label>
                            @php $audience = old('audience', 'all'); @endphp
                            <div class="form-check form-check-inline">
                                <input type="radio" class="form-check-input audience-radio" id="audience_all" name="audience" value="all" {{ $audience == 'all' ? 'checked' : '' }}>
                                <label class="form-check-label" for="audience_all">{{ __('All Users') }}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="radio" class="form-check-input audience-radio" id="audience_roles" name="audience" value="roles" {{ $audience == 'roles' ? 'checked' : '' }}>
                                <label class="form-check-label" for="audience_roles">{{ __('Specific User Type') }}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="radio" class="form-check-input audience-radio" id="audience_specific" name="audience" value="specific" {{ $audience == 'specific' ? 'checked' : '' }}>
                                <label class="form-check-label" for="audience_specific">{{ __('Specific Users') }}</label>
                            </div>
                            @error('audience')
                                <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="mb-4 col-md-12" id="roles_select_box">
                            <label class="form-label d-block">{{ __('User Type') }}</label>
                            @php
                                $audienceLabels = [
                                    'customers' => __('Customers'),
                                    'drivers' => __('Drivers'),
                                    'restaurant_owners' => __('Restaurant Owners'),
                                    'delivery_riders' => __('Delivery Riders'),
                                ];
                                $oldRoles = collect(old('roles'));
                            @endphp
                            @foreach ($audienceLabels as $key => $label)
                                <div class="form-check form-check-inline">
                                    <input type="checkbox" class="form-check-input" id="role_{{ $key }}" name="roles[]" value="{{ $key }}" {{ $oldRoles->contains($key) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="role_{{ $key }}">{{ $label }}</label>
                                </div>
                            @endforeach
                            @error('roles')
                                <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="mb-4 col-md-12" id="users_select_box">
                            <label class="form-label" for="user_ids">{{ __('Users') }}</label>
                            <select id="user_ids" name="user_ids[]"
                                class="form-select @error('user_ids') is-invalid @enderror" multiple>
                                @foreach ($selectedUsers as $user)
                                    <option value="{{ $user->id }}" selected>
                                        {{ $user->email ? "{$user->name} ({$user->email})" : $user->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ __('Type to search by name or email.') }}</small>
                            @error('user_ids')
                                <span class="invalid-feedback d-block" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-4 col-md-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_popup" name="is_popup"
                                    value="1" {{ old('is_popup') ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_popup">{{ __('Show as a popup on open (not just a bell notification)') }}</label>
                            </div>
                        </div>
                        <div class="mb-4 col-md-12">
                            <label for="title" class="form-label">{{ __('Title') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('title') is-invalid @enderror" type="text" id="title"
                                name="title" required placeholder="{{ __('Enter title') }}" autofocus
                                value="{{ old('title') }}" />
                            @error('title')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-12">
                            <label for="message" class="form-label">{{ __('Message') }}</label><span
                                class="text-danger">*</span>
                            <textarea class="form-control @error('message') is-invalid @enderror" id="message" name="message"
                                placeholder="{{ __('Enter message') }}" required cols="10" rows="5">{{ old('message') }}</textarea>
                            @error('message')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary me-3">{{ __('Send Notification') }}</button>
                    </div>
                </form>
            </div>
            <!-- /Account -->
        </div>
    </div>
@endsection

@section('script')
    <!-- Vendors JS -->
    <script>
        $(document).ready(function() {
            // Manual select2 init (not the .select2 class) with a remote
            // search, since preloading every active user doesn't scale once
            // there are hundreds of customers/drivers/restaurant owners/riders.
            $('#user_ids').wrap('<div class="position-relative"></div>').select2({
                placeholder: '{{ __("Search users by name or email...") }}',
                dropdownParent: $('#user_ids').parent(),
                minimumInputLength: 2,
                ajax: {
                    url: '{{ route("dashboard.notifications.search-users") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { q: params.term };
                    },
                    processResults: function(data) {
                        return { results: data.results };
                    },
                    cache: true,
                },
            });

            function toggleAudienceBoxes() {
                const audience = $('input[name="audience"]:checked').val();

                if (audience === 'roles') {
                    $('#roles_select_box').show();
                } else {
                    $('#roles_select_box').hide();
                }

                if (audience === 'specific') {
                    $('#users_select_box').show();
                } else {
                    $('#users_select_box').hide();
                    $('#user_ids').val(null).trigger('change');
                }
            }

            // Run on page load
            toggleAudienceBoxes();

            // Run on radio toggle
            $('.audience-radio').on('change', function() {
                toggleAudienceBoxes();
            });
        });
    </script>
@endsection
