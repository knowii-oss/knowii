<?php

namespace App\Contracts\Notifications;

use App\Models\Community;
use App\Models\Space;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The level that a member chooses for a space (#1475, ADR 0042, design 2.2): Everything (`all`), Only what is for me
 * (`mine`), Muted (`none`). No choice means the default of the space. Only a member of the community who can view the
 * space has a level for it.
 *
 * A level is shown as `{space_cuid, level, chosen, default}`: `level` is the one that applies, `chosen` says whether the
 * member chose it, and `default` is the level that applies without a choice.
 */
interface ChoosesSpaceLevels
{
    /**
     * The level of the member for one space
     *
     * @return array{space_cuid: string, level: string, chosen: bool, default: string}
     *
     * @throws AuthorizationException
     */
    public function show(User $user, Space $space): array;

    /**
     * The levels of the member for every space of the community that they can view, in the order of the navigation: one
     * call for a settings screen. A link space has no level and is not listed.
     *
     * @return list<array{space_cuid: string, space_name: string, space_slug: string, space_type: string, space_group_cuid: string, space_group_name: string, level: string, chosen: bool, default: string}>
     *
     * @throws AuthorizationException
     */
    public function forCommunity(User $user, Community $community): array;

    /**
     * The member chooses a level for the space. "Muted" deletes their open rows of the space.
     *
     * @param  array<mixed>  $input  level: all, mine or none
     * @return array{space_cuid: string, level: string, chosen: bool, default: string}
     *
     * @throws AuthorizationException
     */
    public function set(User $user, Space $space, array $input): array;

    /**
     * The member goes back to the default of the space: their choice is forgotten.
     *
     * @return array{space_cuid: string, level: string, chosen: bool, default: string}
     *
     * @throws AuthorizationException
     */
    public function reset(User $user, Space $space): array;
}
