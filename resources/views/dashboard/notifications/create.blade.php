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

                        <div class="mb-4 col-md-12 position-relative" id="users_select_box">
                            <label class="form-label" for="user_search_input">{{ __('Users') }}</label>
                            <input type="text" id="user_search_input" class="form-control @error('user_ids') is-invalid @enderror"
                                placeholder="{{ __('Type to search by name or email...') }}" autocomplete="off">
                            <div id="user_search_results" class="list-group position-absolute w-100 shadow-sm"
                                style="z-index: 1050; display: none; max-height: 250px; overflow-y: auto; background-color: var(--bs-body-bg, #fff); border: 1px solid rgba(0,0,0,.15); border-radius: 0.375rem;"></div>

                            <div id="selected_users_chips" class="d-flex flex-wrap gap-2 mt-2"></div>

                            {{-- The actual form data -- a plain hidden multi-select the widget above
                                 manages via jQuery (append/remove <option>), so the server-side
                                 handling (request->user_ids) needed no changes. --}}
                            <select id="user_ids" name="user_ids[]" multiple style="display: none;">
                                @foreach ($selectedUsers as $user)
                                    @php $label = $user->email ? "{$user->name} ({$user->email})" : $user->name; @endphp
                                    <option value="{{ $user->id }}" selected data-label="{{ $label }}">{{ $label }}</option>
                                @endforeach
                            </select>
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
            // Plain jQuery remote-search widget for the Users field --
            // deliberately not using select2's built-in `ajax` option, which
            // silently never invoked its own data/processResults callbacks on
            // this page despite initializing without error (confirmed via the
            // exact same request succeeding when called directly with
            // $.getJSON). This gives full control and is easy to debug.
            const searchUrl = '{{ route("dashboard.notifications.search-users") }}';
            let searchTimeout = null;

            function renderSelectedChips() {
                const $chips = $('#selected_users_chips').empty();
                $('#user_ids option').each(function() {
                    const $opt = $(this);
                    const $chip = $('<span>', {
                        class: 'badge bg-label-primary d-flex align-items-center gap-2 p-2',
                        text: $opt.data('label') || $opt.text(),
                    });
                    $('<button>', {
                        type: 'button',
                        class: 'btn-close',
                        style: 'font-size: 0.6rem;',
                        'aria-label': 'Remove',
                    }).on('click', function() {
                        $opt.remove();
                        renderSelectedChips();
                    }).appendTo($chip);
                    $chips.append($chip);
                });
            }

            $('#user_search_input').on('input', function() {
                const term = $(this).val().trim();
                clearTimeout(searchTimeout);

                if (term.length < 2) {
                    $('#user_search_results').hide().empty();
                    return;
                }

                searchTimeout = setTimeout(function() {
                    $.getJSON(searchUrl, { q: term })
                        .done(function(data) {
                            const $results = $('#user_search_results').empty();
                            const results = (data && data.results) || [];

                            if (!results.length) {
                                $results.append(
                                    $('<div>', { class: 'list-group-item text-muted', text: '{{ __("No results found") }}' })
                                );
                            } else {
                                results.forEach(function(user) {
                                    $('<button>', {
                                        type: 'button',
                                        class: 'list-group-item list-group-item-action',
                                        text: user.text,
                                    }).on('click', function() {
                                        if ($('#user_ids option[value="' + user.id + '"]').length === 0) {
                                            $('#user_ids').append(
                                                $('<option>', { value: user.id, selected: true, 'data-label': user.text, text: user.text })
                                            );
                                            renderSelectedChips();
                                        }
                                        $('#user_search_input').val('');
                                        $results.hide().empty();
                                    }).appendTo($results);
                                });
                            }

                            $results.show();
                        });
                }, 250);
            });

            // Close the results dropdown when clicking elsewhere
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#users_select_box').length) {
                    $('#user_search_results').hide();
                }
            });

            renderSelectedChips();

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
                    $('#user_search_results').hide().empty();
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
