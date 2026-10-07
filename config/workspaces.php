<?php

/**
 * Admin panel workspaces. "rides" and "delivery" are the two service
 * workspaces a user is switched between; "platform" is the shared area
 * (Customers, Finance, Settings, etc.) and is NOT gated by the switcher --
 * it's always visible to anyone with the matching Spatie permission,
 * regardless of which service workspace is currently selected. See the
 * Phase 1 report for why this reads "shared" rather than "a third switcher
 * option" -- the data model (user_workspaces can still hold 'platform' per
 * user) supports moving to a literal 3-way switcher later without a new
 * migration, just a sidebar/middleware change.
 *
 * route_prefixes maps a dashboard.* route-name prefix to the workspace it
 * requires. Any route name not matched here is unrestricted by workspace
 * (still protected by its own Spatie permission as before).
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
        'dashboard.rides.' => 'rides',
        'dashboard.drivers.' => 'rides',
        'dashboard.custom-rides.' => 'rides',
        'dashboard.live-tracking.' => 'rides',
        'dashboard.vehicle-types.' => 'rides',
        'dashboard.vehicle-type-icons.' => 'rides',
        'dashboard.boost-hours.' => 'rides',
        'dashboard.promo-codes.' => 'rides',
        'dashboard.chauffeur-vehicles.' => 'rides',
        'dashboard.chauffeur-bookings.' => 'rides',

        'dashboard.restaurants.' => 'delivery',
        'dashboard.restaurant-categories.' => 'delivery',
        'dashboard.restaurant-vouchers.' => 'delivery',
        'dashboard.restaurant-owners.' => 'delivery',
        'dashboard.delivery.' => 'delivery',
    ],
];
