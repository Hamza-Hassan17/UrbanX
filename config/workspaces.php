<?php

/**
 * Admin panel workspaces. All three -- "rides", "delivery", "platform" --
 * are real, switchable workspaces as of this revision (Platform used to be
 * "always visible regardless of selection"; that's been replaced per
 * explicit follow-up instruction -- see git history around this file for
 * the earlier approach if it's ever needed again).
 *
 * route_prefixes maps a dashboard.* route name (exact name or a prefix
 * ending in '.') to the workspace it requires. Any route name not matched
 * here is unrestricted by workspace (still protected by its own Spatie
 * permission as before) -- that's deliberate for things everyone needs
 * regardless of workspace, like the notification bell's own inbox page
 * (dashboard.notifications.index) or a driver's profile/KYC pages
 * (dashboard.drivers.show/update/verification.*), which aren't
 * workspace-specific even though the two LIST pages that link into them
 * are.
 */
return [
    'workspaces' => [
        'rides' => [
            'label' => 'Rides',
            'icon' => 'ti ti-steering-wheel',
            'landing_route' => 'dashboard',
        ],
        'delivery' => [
            'label' => 'Delivery',
            'icon' => 'ti ti-chef-hat',
            'landing_route' => 'dashboard',
        ],
        'platform' => [
            'label' => 'Platform',
            'icon' => 'ti ti-apps',
            'landing_route' => 'dashboard',
        ],
    ],

    'route_prefixes' => [
        // Rides
        'dashboard.rides.' => 'rides',
        'dashboard.drivers.index' => 'rides',
        'dashboard.drivers.pending-verifications' => 'rides',
        'dashboard.custom-rides.' => 'rides',
        'dashboard.live-tracking.' => 'rides',
        'dashboard.vehicle-types.' => 'rides',
        'dashboard.vehicle-type-icons.' => 'rides',
        'dashboard.boost-hours.' => 'rides',
        'dashboard.promo-codes.' => 'rides',
        'dashboard.chauffeur-vehicles.' => 'rides',
        'dashboard.chauffeur-bookings.' => 'rides',

        // Delivery
        'dashboard.restaurants.' => 'delivery',
        'dashboard.restaurant-categories.' => 'delivery',
        'dashboard.restaurant-vouchers.' => 'delivery',
        'dashboard.restaurant-owners.' => 'delivery',
        'dashboard.delivery.' => 'delivery',
        'dashboard.delivery-riders.' => 'delivery',

        // Platform
        'dashboard.user.' => 'platform',
        'dashboard.archived-user.' => 'platform',
        'dashboard.admin-users.' => 'platform',
        'dashboard.customers.' => 'platform',
        'dashboard.finance.' => 'platform',
        'dashboard.pricing-fees.' => 'platform',
        'dashboard.payroll.' => 'platform',
        'dashboard.reports.' => 'platform',
        'dashboard.complains.' => 'platform',
        'dashboard.support-requests.' => 'platform',
        'dashboard.announcements.' => 'platform',
        'dashboard.roles.' => 'platform',
        'dashboard.permissions.' => 'platform',
        'dashboard.setting.' => 'platform',
        'dashboard.terms.' => 'platform',
        // Only the Send Notification feature, not the notification bell's
        // own inbox (dashboard.notifications.index) -- everyone needs that
        // regardless of workspace.
        'dashboard.notifications.create' => 'platform',
        'dashboard.notifications.store' => 'platform',
        'dashboard.notifications.search-users' => 'platform',
    ],
];
