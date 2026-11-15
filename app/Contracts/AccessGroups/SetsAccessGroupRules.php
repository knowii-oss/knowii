<?php

namespace App\Contracts\AccessGroups;

use App\Models\AccessGroup;
use App\Models\User;

interface SetsAccessGroupRules
{
    /**
     * Replace the entitlement keys that give members this group (#1386, ADR 0047). The input has `entitlement_keys`: free text
     * checked against a safe pattern. An empty list removes every rule. Owner and admins, only in a community that the
     * operator lists in OIDC_ENTITLEMENT_COMMUNITIES (404 otherwise).
     *
     * @param  array<mixed>  $input
     */
    public function set(User $user, AccessGroup $group, array $input): AccessGroup;
}
