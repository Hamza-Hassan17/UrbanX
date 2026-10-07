<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme" style="background: radial-gradient(50% 50% at 50% 50%, #353535 0%, #000000 100%) !important;">
    <div class="app-brand demo">
        <a href="{{ route('dashboard') }}" class="app-brand-link">
            <span class="app-brand-logo">
                <img style="height: 40px;" src="{{ asset(\App\Helpers\Helper::getLogoLight()) }}" alt="{{env('APP_NAME')}}">
            </span>
            <span class="app-brand-text demo menu-text fw-bold" style="color: #fff;">{{\App\Helpers\Helper::getCompanyName()}}</span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto" style="color: #fff;">
            <i class="ti menu-toggle-icon d-none d-xl-block align-middle"></i>
            <i class="ti ti-x d-block d-xl-none ti-md align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    @php
        $__allowedWorkspaces = auth()->check() ? auth()->user()->allowedWorkspaces() : [];
        $__currentWorkspace = session('workspace');
        $__switchableWorkspaces = array_values(array_intersect(['rides', 'delivery'], $__allowedWorkspaces));
    @endphp
    @if (count($__switchableWorkspaces) > 1)
        <div class="px-4 py-2">
            <select class="form-select form-select-sm" id="workspace-switcher" style="background: #222; color: #fff; border-color: #444;">
                @foreach ($__switchableWorkspaces as $__ws)
                    <option value="{{ $__ws }}" {{ $__currentWorkspace === $__ws ? 'selected' : '' }}>
                        {{ config("workspaces.workspaces.$__ws.label") }}
                    </option>
                @endforeach
            </select>
        </div>
        <form id="workspace-switch-form" action="{{ route('workspace.switch') }}" method="POST" class="d-none">
            @csrf
            <input type="hidden" name="workspace" id="workspace-switch-input">
        </form>
        <script>
            document.getElementById('workspace-switcher').addEventListener('change', function () {
                document.getElementById('workspace-switch-input').value = this.value;
                document.getElementById('workspace-switch-form').submit();
            });
        </script>
    @endif

    <ul class="menu-inner py-1">
        {{--
            Grouped by function per UrbanX_Sidebar_Spec.pdf (+ addendum superseding
            the Riders/Customers naming), not by controller. Restaurant Owners and
            Email Settings have since been built (Tier 1 follow-up, now complete).
            Still deliberately left out: Live Ops, Reviews, Chauffeur Transactions,
            Restaurant Orders, Payroll Export -- none of those pages exist yet.
        --}}

        <!-- 1. Dashboard -->
        <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}" class="menu-link"  style="color: #fff !important;">
                <i class="menu-icon tf-icons ti ti-smart-home"></i>
                <div>{{__('Dashboard')}}</div>
            </a>
        </li>

        <li class="menu-header small">
            <span class="menu-header-text">{{__('Apps & Pages')}}</span>
        </li>

        {{-- 2. Live Ops -- omitted: no dispatch-queue/live-tracking/anomaly-alert backend built yet. --}}

        {{-- 3. Rides -- workspace-gated: only visible while "Rides" is the
             selected service workspace. See config/workspaces.php. --}}
        @if($__currentWorkspace === 'rides')
        @canany(['view ride', 'view custom rides', 'view live tracking', 'view vehicle type', 'create boost hour', 'view promo code', 'view driver'])
            <li class="menu-item {{ request()->routeIs('dashboard.rides.*') || request()->routeIs('dashboard.custom-rides.*') || request()->routeIs('dashboard.live-tracking.*') || request()->routeIs('dashboard.vehicle-types.*') || request()->routeIs('dashboard.boost-hours.*') || request()->routeIs('dashboard.promo-codes.*') || request()->routeIs('dashboard.drivers.*') ? 'open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-steering-wheel"></i>
                    <div>{{__('Rides')}}</div>
                </a>
                <ul class="menu-sub">
                    @can(['view ride'])
                        <li class="menu-item {{ request()->routeIs('dashboard.rides.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.rides.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('All Rides')}}</div>
                            </a>
                        </li>
                    @endcan
                    {{--
                        Moved here from Users per the sidebar restructure spec --
                        ride-hailing drivers are a Rides concept. Confirmed in code
                        that Chauffeur/Rentals has no driver-role tie-in at all
                        (ChauffeursVehicle/ChauffeursBooking never reference the
                        driver role), so there's no "same table, two places" case
                        to also list this under Chauffeur/Rentals.
                    --}}
                    @can(['view driver'])
                        <li class="menu-item {{ request()->routeIs('dashboard.drivers.index') ? 'active' : '' }}">
                            <a href="{{route('dashboard.drivers.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Drivers')}}</div>
                            </a>
                        </li>
                        <li class="menu-item {{ request()->routeIs('dashboard.drivers.pending-verifications') ? 'active' : '' }}">
                            <a href="{{route('dashboard.drivers.pending-verifications')}}" class="menu-link d-flex justify-content-between align-items-center" style="color: #fff !important;">
                                <div>{{__('Pending Verifications')}}</div>
                                @php
                                    $pendingVerificationCount = \App\Models\DriverVerification::where('status', 'submitted')->count();
                                @endphp
                                @if ($pendingVerificationCount > 0)
                                    <span class="badge bg-warning rounded-pill">{{ $pendingVerificationCount }}</span>
                                @endif
                            </a>
                        </li>
                    @endcan
                    @can(['view custom rides'])
                        <li class="menu-item {{ request()->routeIs('dashboard.custom-rides.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.custom-rides.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Manual Ride Assignment')}}</div>
                            </a>
                        </li>
                    @endcan
                    @can(['view live tracking'])
                        <li class="menu-item {{ request()->routeIs('dashboard.live-tracking.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.live-tracking.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Live Tracking')}}</div>
                            </a>
                        </li>
                    @endcan
                    @can(['view vehicle type'])
                        <li class="menu-item {{ request()->routeIs('dashboard.vehicle-types.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.vehicle-types.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Vehicle Types')}}</div>
                            </a>
                        </li>
                    @endcan
                    @can(['create boost hour'])
                        <li class="menu-item {{ request()->routeIs('dashboard.boost-hours.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.boost-hours.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Boost Hours')}}</div>
                            </a>
                        </li>
                    @endcan
                    @can(['view promo code'])
                        <li class="menu-item {{ request()->routeIs('dashboard.promo-codes.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.promo-codes.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Promo Codes')}}</div>
                            </a>
                        </li>
                    @endcan
                </ul>
            </li>
        @endcanany
        @endif

        {{-- 4. Chauffeur / Rentals -- same workspace as Rides for now; the
             spec calls out that this group must be self-contained enough to
             move into its own workspace later purely via config/workspaces.php,
             which is why it's wrapped separately rather than merged into the
             Rides @if above. --}}
        @if($__currentWorkspace === 'rides')
        @canany(['view chauffeur vehicle', 'view chauffeur booking'])
            <li class="menu-item {{ request()->routeIs('dashboard.chauffeur-vehicles.*') || request()->routeIs('dashboard.chauffeur-bookings.*') ? 'open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-car"></i>
                    <div>{{__('Chauffeur / Rentals')}}</div>
                </a>
                <ul class="menu-sub">
                    @can(['view chauffeur vehicle'])
                        <li class="menu-item {{ request()->routeIs('dashboard.chauffeur-vehicles.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.chauffeur-vehicles.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Vehicles')}}</div>
                            </a>
                        </li>
                    @endcan
                    @can(['view chauffeur booking'])
                        <li class="menu-item {{ request()->routeIs('dashboard.chauffeur-bookings.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.chauffeur-bookings.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Bookings')}}</div>
                            </a>
                        </li>
                    @endcan
                    {{-- Transactions -- omitted: no dashboard page exists for this yet. --}}
                </ul>
            </li>
        @endcanany
        @endif

        {{-- 5. Restaurants -- workspace-gated: only visible while "Delivery"
             is the selected service workspace. --}}
        @if($__currentWorkspace === 'delivery')
        @canany(['view restaurant', 'view restaurant category', 'view restaurant voucher', 'view user'])
            <li class="menu-item {{ request()->routeIs('dashboard.restaurants.*') || request()->routeIs('dashboard.restaurant-categories.*') || request()->routeIs('dashboard.restaurant-vouchers.*') || request()->routeIs('dashboard.restaurant-owners.*') ? 'open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-chef-hat"></i>
                    <div>{{__('Restaurants')}}</div>
                </a>
                <ul class="menu-sub">
                    @can(['view restaurant'])
                        <li class="menu-item {{ request()->routeIs('dashboard.restaurants.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.restaurants.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Restaurants')}}</div>
                            </a>
                        </li>
                    @endcan
                    @can(['view restaurant category'])
                        <li class="menu-item {{ request()->routeIs('dashboard.restaurant-categories.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.restaurant-categories.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Categories')}}</div>
                            </a>
                        </li>
                    @endcan
                    {{-- Orders -- omitted: no standalone dashboard list page exists yet, only an inline update route. --}}
                    {{-- Moved here from Users per the sidebar restructure spec -- restaurant owner accounts are a Restaurants concept, not a generic Users one. --}}
                    @can(['view user'])
                        <li class="menu-item {{ request()->routeIs('dashboard.restaurant-owners.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.restaurant-owners.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Restaurant Owners')}}</div>
                            </a>
                        </li>
                    @endcan
                    @can(['view restaurant voucher'])
                        <li class="menu-item {{ request()->routeIs('dashboard.restaurant-vouchers.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.restaurant-vouchers.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Voucher Codes')}}</div>
                            </a>
                        </li>
                    @endcan
                </ul>
            </li>
        @endcanany
        @endif

        {{-- 6. Users -- scoped to Customers (+ Archived Users) only per the sidebar
             restructure spec. Drivers moved to Rides, Restaurant Owners moved to
             Restaurants, Admin Panel Users promoted to its own top-level item below. --}}
        @canany(['view user', 'view archived user'])
            <li class="menu-item {{ request()->routeIs('dashboard.user.*') || request()->routeIs('dashboard.archived-user.*') ? 'open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-users"></i>
                    <div>{{__('Users')}}</div>
                </a>
                <ul class="menu-sub">
                    {{--
                        "Riders/Customers" from the original spec was superseded by the
                        addendum -- rider is a vestigial, unused role (verified: zero
                        activity, zero references anywhere in app/routes, real delivery
                        couriers are driver-role + vehicle-type flag instead). Left out
                        of the sidebar entirely; "Customers" below is role=user only.
                    --}}
                    @can(['view user'])
                        <li class="menu-item {{ request()->routeIs('dashboard.user.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.user.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Customers')}}</div>
                            </a>
                        </li>
                    @endcan
                    @can(['view archived user'])
                        <li class="menu-item {{ request()->routeIs('dashboard.archived-user.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.archived-user.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Archived Users')}}</div>
                            </a>
                        </li>
                    @endcan
                </ul>
            </li>
        @endcanany

        {{--
            7. Admin Panel Users -- own top-level item per the spec, no longer
            nested under Users. Now gated by its own 'view admin user' permission
            instead of inheriting 'view user', since managing other admin accounts
            is more sensitive than viewing customers.
        --}}
        @can(['view admin user'])
            <li class="menu-item {{ request()->routeIs('dashboard.admin-users.*') ? 'active' : '' }}">
                <a href="{{ route('dashboard.admin-users.index') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-user-shield"></i>
                    <div>{{__('Admin Panel Users')}}</div>
                </a>
            </li>
        @endcan

        {{-- 8. Finance (Tax & Commission, Reports, Payroll) --}}
        @can(['export payroll'])
            <li class="menu-item {{ request()->routeIs('dashboard.finance.*') || request()->routeIs('dashboard.payroll.*') ? 'open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-coin"></i>
                    <div>{{__('Finance')}}</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item {{ request()->routeIs('dashboard.finance.tax-commission') ? 'active' : '' }}">
                        <a href="{{route('dashboard.finance.tax-commission')}}" class="menu-link" style="color: #fff !important;">
                            <div>{{__('Tax & Commission')}}</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('dashboard.finance.reports*') ? 'active' : '' }}">
                        <a href="{{route('dashboard.finance.reports')}}" class="menu-link" style="color: #fff !important;">
                            <div>{{__('Reports')}}</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('dashboard.payroll.*') ? 'active' : '' }}">
                        <a href="{{route('dashboard.payroll.index')}}" class="menu-link" style="color: #fff !important;">
                            <div>{{__('Payroll')}}</div>
                        </a>
                    </li>
                </ul>
            </li>
        @endcan

        {{-- 8b. Admin Panel User Activity (operator/driver job reports) --}}
        @can(['view report'])
            <li class="menu-item {{ request()->routeIs('dashboard.reports.*') ? 'active' : '' }}">
                <a href="{{route('dashboard.reports.index')}}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-report-analytics"></i>
                    <div>{{__('Admin Panel User Activity')}}</div>
                </a>
            </li>
        @endcan

        {{-- 9. Complaints --}}
        @can(['create complain'])
            <li class="menu-item {{ request()->routeIs('dashboard.complains.*') ? 'active' : '' }}">
                <a href="{{ route('dashboard.complains.index') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-message-exclamation"></i>
                    <div>{{__('Complaints')}}</div>
                </a>
            </li>
        @endcan

        {{-- 9b. Driver Support Requests --}}
        @can(['view support requests'])
            <li class="menu-item {{ request()->routeIs('dashboard.support-requests.*') ? 'active' : '' }}">
                <a href="{{ route('dashboard.support-requests.index') }}" class="menu-link d-flex justify-content-between align-items-center" style="color: #fff !important;">
                    <div><i class="menu-icon tf-icons ti ti-message-2"></i>{{__('Support Requests')}}</div>
                    @php
                        $pendingSupportRequestCount = \App\Models\SupportRequest::where('status', 'pending')->count();
                    @endphp
                    @if ($pendingSupportRequestCount > 0)
                        <span class="badge bg-warning rounded-pill">{{ $pendingSupportRequestCount }}</span>
                    @endif
                </a>
            </li>
        @endcan

        {{-- 10. Reviews -- omitted: no dashboard controller/view exists for DriverReview or RestaurantReview yet. --}}

        {{-- 11. Announcements --}}
        @can(['view announcement'])
            <li class="menu-item {{ request()->routeIs('dashboard.announcements.*') ? 'active' : '' }}">
                <a href="{{ route('dashboard.announcements.index') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-speakerphone"></i>
                    <div>{{__('Announcements')}}</div>
                </a>
            </li>
        @endcan

        {{-- 12. Settings --}}
        @canany(['view role', 'view permission', 'view setting'])
            <li class="menu-item {{ request()->routeIs('dashboard.roles.*') || request()->routeIs('dashboard.permissions.*') || request()->routeIs('dashboard.setting.*') ? 'open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-settings"></i>
                    <div>{{__('Settings')}}</div>
                </a>
                <ul class="menu-sub">
                    @can(['view role'])
                        <li class="menu-item {{ request()->routeIs('dashboard.roles.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.roles.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Roles & Permissions')}}</div>
                            </a>
                        </li>
                    @endcan
                    @can(['view permission'])
                        <li class="menu-item {{ request()->routeIs('dashboard.permissions.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.permissions.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Permissions')}}</div>
                            </a>
                        </li>
                    @endcan
                    @can(['view setting'])
                        <li class="menu-item {{ request()->routeIs('dashboard.setting.*') && request()->query('tab') !== 'email' ? 'active' : '' }}">
                            <a href="{{route('dashboard.setting.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Company / System Settings')}}</div>
                            </a>
                        </li>
                        {{--
                            Same page/controller as above -- Settings already has its own
                            in-page tabs (public/assets/js/custom-js/settings.js reads
                            ?tab=... on load), this just deep-links straight to the email
                            tab rather than duplicating a controller/view for it.
                        --}}
                        <li class="menu-item {{ request()->routeIs('dashboard.setting.*') && request()->query('tab') === 'email' ? 'active' : '' }}">
                            <a href="{{route('dashboard.setting.index')}}?tab=email" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Email Settings')}}</div>
                            </a>
                        </li>
                    @endcan
                </ul>
            </li>
        @endcanany

        {{--
            Not part of the 11-group spec, left untouched and in place since the
            doc doesn't mention it: Send Notification.
        --}}
        @can(['create notification'])
            <li class="menu-item {{ request()->routeIs('dashboard.notifications.create') ? 'active' : '' }}">
                <a href="{{ route('dashboard.notifications.create') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-bell"></i>
                    <div>{{__('Send Notification')}}</div>
                </a>
            </li>
        @endcan
    </ul>
</aside>
