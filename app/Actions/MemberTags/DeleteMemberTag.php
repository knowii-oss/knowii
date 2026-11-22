<?php

namespace App\Actions\MemberTags;

use App\Contracts\MemberTags\DeletesMemberTags;
use App\Models\MemberTag;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class DeleteMemberTag implements DeletesMemberTags
{
    /**
     * Delete a member tag (#1389, ADR 0060): the owner and the admins. The cascade of the foreign key removes it from every
     * member who had it, and later from every invitation and invite link that carries it (#1390, #1391). Nothing undoes it.
     */
    final public function delete(User $user, MemberTag $tag): void
    {
        $community = $tag->community;
        Gate::forUser($user)->authorize('manageMemberTags', $community);

        $tag->delete();

        Log::info('Member tag deleted', ['community' => $community->cuid, 'tag' => $tag->cuid]);
    }
}
