<?php

namespace App\Actions\CommunityMembers;

use App\Enums\KnowiiCommunityMemberRole;
use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait FindsCommunityMembers
{
    /**
     * The member whose user cuid is given, with their role (null: the owner). A user who is not a member gives a 404.
     *
     * @return array{0: User, 1: ?KnowiiCommunityMemberRole}
     */
    private function findMember(Community $community, string $memberCuid): array
    {
        $target = User::query()->where('cuid', $memberCuid)->first();
        if ($target === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$memberCuid]);
        }

        if ($target->ownsCommunity($community)) {
            return [$target, null];
        }

        $membership = $community->getMemberById($target->id);
        if ($membership === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$memberCuid]);
        }

        return [$target, $membership->role];
    }
}
