<?php

namespace App\Actions\InviteLinks;

use App\Contracts\InviteLinks\DeletesInviteLinks;
use App\Models\InviteLink;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class DeleteInviteLink implements DeletesInviteLinks
{
    /**
     * Delete an invite link (#1391, ADR 0062): the owner and the admins. It stops working at once. The cascade of the foreign
     * keys removes its groups, its tags and the list of who joined through it. The members who joined through it stay,
     * with their groups and tags. Nothing undoes it.
     */
    final public function delete(User $user, InviteLink $link): void
    {
        $community = $link->community;
        Gate::forUser($user)->authorize('manageInviteLinks', $community);

        $link->delete();

        Log::info('Invite link deleted', ['community' => $community->cuid, 'link' => $link->cuid]);
    }
}
