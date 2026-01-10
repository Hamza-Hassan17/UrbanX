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
        <!-- Dashboards -->
        <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}" class="menu-link"  style="color: #fff !important;">
                <i class="menu-icon tf-icons ti ti-smart-home"></i>
                <div>{{__('Dashboard')}}</div>
            </a>
        </li>

        <!-- Apps & Pages -->
        <li class="menu-header small">
            <span class="menu-header-text">{{__('Apps & Pages')}}</span>
        </li>
        @can(['view driver'])
            <li class="menu-item {{ request()->routeIs('dashboard.drivers.*') ? 'active' : '' }}">
                <a href="{{ route('dashboard.drivers.index') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-steering-wheel"></i>
                    <div>{{__('Drivers')}}</div>
                </a>
            </li>
        @endcan
        @can(['view promo code'])
            <li class="menu-item {{ request()->routeIs('dashboard.promo-codes.*') ? 'active' : '' }}">
                <a href="{{ route('dashboard.promo-codes.index') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-tag"></i>
                    <div>{{__('Promo Codes')}}</div>
                </a>
            </li>
        @endcan
        @can(['view vehicle type'])
            <li class="menu-item {{ request()->routeIs('dashboard.vehicle-types.*') ? 'active' : '' }}">
                <a href="{{ route('dashboard.vehicle-types.index') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-car"></i>
                    <div>{{__('Vehicle Types')}}</div>
                </a>
            </li>
        @endcan
        @can(['create notification'])
            <li class="menu-item {{ request()->routeIs('dashboard.notifications.create') ? 'active' : '' }}">
                <a href="{{ route('dashboard.notifications.create') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-bell"></i>
                    <div>{{__('Send Notification')}}</div>
                </a>
            </li>
        @endcan
        @can(['create complain'])
            <li class="menu-item {{ request()->routeIs('dashboard.complains.*') ? 'active' : '' }}">
                <a href="{{ route('dashboard.complains.index') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-message-exclamation"></i>
                    <div>{{__('Complains')}}</div>
                </a>
            </li>
        @endcan
        @can(['create boost hour'])
            <li class="menu-item {{ request()->routeIs('dashboard.boost-hours.*') ? 'active' : '' }}">
                <a href="{{ route('dashboard.boost-hours.index') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-clock"></i>
                    <div>{{__('Boost Hours')}}</div>
                </a>
            </li>
        @endcan
        @canany(['view chauffeur vehicle', 'view chauffeur booking'])
            <li class="menu-item {{ request()->routeIs('dashboard.chauffeur-vehicles.*') || request()->routeIs('dashboard.chauffeur-bookings.*') ? 'open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-steering-wheel"></i>
                    <div>{{__('Chauffeurs')}}</div>
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
                                <div>{{__('Booking')}}</div>
                            </a>
                        </li>
                    @endcan
                </ul>
            </li>
        @endcan
        @canany(['view user', 'view archived user'])
            <li class="menu-item {{ request()->routeIs('dashboard.user.*') || request()->routeIs('dashboard.archived-user.*') ? 'open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-users"></i>
                    <div>{{__('Users')}}</div>
                </a>
                <ul class="menu-sub">
                    @can(['view user'])
                        <li class="menu-item {{ request()->routeIs('dashboard.user.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.user.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('All Users')}}</div>
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
        @endcan
        @canany(['view role', 'view permission'])
            <li class="menu-item {{ request()->routeIs('dashboard.roles.*') || request()->routeIs('dashboard.permissions.*') ? 'open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle" style="color: #fff !important;">
                    {{-- <i class="menu-icon tf-icons ti ti-settings"></i> --}}
                    <i class="menu-icon tf-icons ti ti-shield-lock"></i>
                    <div>{{__('Roles & Permissions')}}</div>
                </a>
                <ul class="menu-sub">
                    @can(['view role'])
                        <li class="menu-item {{ request()->routeIs('dashboard.roles.*') ? 'active' : '' }}">
                            <a href="{{route('dashboard.roles.index')}}" class="menu-link" style="color: #fff !important;">
                                <div>{{__('Roles')}}</div>
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
                </ul>
            </li>
        @endcan
        @can(['view setting'])
            <li class="menu-item {{ request()->routeIs('dashboard.setting.*') ? 'active' : '' }}">
                <a href="{{ route('dashboard.setting.index') }}" class="menu-link" style="color: #fff !important;">
                    <i class="menu-icon tf-icons ti ti-settings"></i>
                    <div>{{__('Settings')}}</div>
                </a>
            </li>
        @endcan
    </ul>
</aside>
