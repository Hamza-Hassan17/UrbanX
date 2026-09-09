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

        {{-- 3. Rides --}}
        @canany(['view ride', 'view custom rides', 'view vehicle type', 'create boost hour', 'view promo code'])
            <li class="menu-item {{ request()->routeIs('dashboard.rides.*') || request()->routeIs('dashboard.custom-rides.*') || request()->routeIs('dashboard.vehicle-types.*') || request()->routeIs('dashboard.boost-hours.*') || request()->routeIs('dashboard.promo-codes.*') ? 'open' : '' }}">
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
                    @can(['view custom rides'])
                        <li class="menu-item {{ request()->routeIs('dashboard.custom-rides.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.custom-rides.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Manual Ride Assignment')}}</div>
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

        {{-- 4. Chauffeur / Rentals --}}
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

        {{-- 5. Restaurants --}}
        @canany(['view restaurant', 'view restaurant category', 'view restaurant voucher'])
            <li class="menu-item {{ request()->routeIs('dashboard.restaurants.*') || request()->routeIs('dashboard.restaurant-categories.*') || request()->routeIs('dashboard.restaurant-vouchers.*') ? 'open' : '' }}">
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

        {{-- 6. Users --}}
        @canany(['view driver', 'view user', 'view archived user'])
            <li class="menu-item {{ request()->routeIs('dashboard.drivers.*') || request()->routeIs('dashboard.user.*') || request()->routeIs('dashboard.admin-users.*') || request()->routeIs('dashboard.restaurant-owners.*') || request()->routeIs('dashboard.archived-user.*') ? 'open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-users"></i>
                    <div>{{__('Users')}}</div>
                </a>
                <ul class="menu-sub">
                    @can(['view driver'])
                        <li class="menu-item {{ request()->routeIs('dashboard.drivers.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.drivers.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Drivers')}}</div>
                            </a>
                        </li>
                    @endcan
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
                        <li class="menu-item {{ request()->routeIs('dashboard.restaurant-owners.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.restaurant-owners.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Restaurant Owners')}}</div>
                            </a>
                        </li>
                        <li class="menu-item {{ request()->routeIs('dashboard.admin-users.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.admin-users.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Admin Panel Users')}}</div>
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

        {{-- 7. Reports & Payroll --}}
        @can(['view report'])
            <li class="menu-item {{ request()->routeIs('dashboard.reports.*') ? 'open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-report-analytics"></i>
                    <div>{{__('Reports & Payroll')}}</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item {{ request()->routeIs('dashboard.reports.*') ? 'active' : '' }}">
                        <a href="{{route('dashboard.reports.index')}}" class="menu-link" style="color: #fff !important;">
                            <div>{{__('Operator / Driver Job Reports')}}</div>
                        </a>
                    </li>
                    {{-- Weekly Payroll Export -- omitted: needs real scoping (export format, data source) before building. --}}
                </ul>
            </li>
        @endcan

        {{-- 8. Complaints --}}
        @can(['create complain'])
            <li class="menu-item {{ request()->routeIs('dashboard.complains.*') ? 'active' : '' }}">
                <a href="{{ route('dashboard.complains.index') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-message-exclamation"></i>
                    <div>{{__('Complaints')}}</div>
                </a>
            </li>
        @endcan

        {{-- 9. Reviews -- omitted: no dashboard controller/view exists for DriverReview or RestaurantReview yet. --}}

        {{-- 10. Announcements --}}
        @can(['view announcement'])
            <li class="menu-item {{ request()->routeIs('dashboard.announcements.*') ? 'active' : '' }}">
                <a href="{{ route('dashboard.announcements.index') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-speakerphone"></i>
                    <div>{{__('Announcements')}}</div>
                </a>
            </li>
        @endcan

        {{-- 11. Settings --}}
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
