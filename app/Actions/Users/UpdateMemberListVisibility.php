<?php

namespace App\Actions\Users;

use App\Contracts\Users\UpdatesMemberListVisibility;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Show or hide a member in the member list of their communities (#1466). Hidden, the other members do not find the
 * member in a member list; the managers still do, and the team of a community is always listed (CommunityMemberQuery).
 * The one writer of the column: the import of a community calls it too, and only ever to hide.
 */
class UpdateMemberListVisibility implements UpdatesMemberListVisibility
{
    final public function update(User $user, array $input): User
    {
        /** @var array{show_in_member_list: bool} $validated */
        $validated = Validator::make($input, [
            'show_in_member_list' => ['required', 'boolean'],
        ])->validate();

        $user->forceFill(['show_in_member_list' => (bool) $validated['show_in_member_list']])->save();

        Log::info('Member list visibility changed', ['user' => $user->cuid, 'shown' => (bool) $validated['show_in_member_list']]);

        return $user;
    }
}
