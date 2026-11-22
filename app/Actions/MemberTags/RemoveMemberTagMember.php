<?php

namespace App\Actions\MemberTags;

use App\Contracts\MemberTags\RemovesMemberTagMembers;
use App\Models\MemberTag;
use App\Models\User;
use App\Support\MemberTagAssignments;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class RemoveMemberTagMember implements RemovesMemberTagMembers
{
    /**
     * Take a member tag off a member (#1389, ADR 0060): the same people who put it. A member who does not have the tag
     * gives a 404.
     */
    final public function remove(User $user, MemberTag $tag, string $memberCuid): void
    {
        $community = $tag->community;
        Gate::forUser($user)->authorize('assignMemberTags', $community);

        $member = User::query()->where('cuid', $memberCuid)->first();
        if ($member === null || ! MemberTagAssignments::remove($tag, $member)) {
            throw (new ModelNotFoundException)->setModel(User::class, [$memberCuid]);
        }

        Log::info('Member tag removed from a member', ['community' => $community->cuid, 'tag' => $tag->cuid, 'member' => $memberCuid]);
    }
}
