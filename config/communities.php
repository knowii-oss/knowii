<?php

/*
 * Reserved community names (#1340, ADR 0036). The name of a shared community is unique and first come, first served
 * (ADR 0033): without a reserved list, anyone could take "Support" or the name of the platform before its
 * administrators do.
 */

$list = static fn (string $value): array => array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));

return [
    // Names that members could take for the platform itself or for its team. A name is compared like ADR 0033
    // compares names: whatever the case, and by the address (slug) that it gives.
    'reserved_names' => [
        'Admin',
        'Admins',
        'Administrator',
        'Administrators',
        'Moderator',
        'Moderators',
        'Staff',
        'Team',
        'Support',
        'Help',
        'Official',
        'Security',
        'System',
        'Knowii',
        'Knowii Community',
        'Knowii Platform',
        'Knowii Team',
        'Knowii Support',
        'Knowii Official',
    ],

    // More names for this instance (your brand, your products), separated by commas
    'extra_reserved_names' => $list((string) env('COMMUNITY_RESERVED_NAMES', '')),

    // Email addresses of the accounts that can use a reserved name (the administrators of the instance), separated
    // by commas. The address of the account must be verified.
    'reserved_names_owners' => array_map('mb_strtolower', $list((string) env('COMMUNITY_RESERVED_NAMES_OWNERS', ''))),
];
