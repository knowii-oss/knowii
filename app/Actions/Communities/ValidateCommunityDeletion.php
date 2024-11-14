<?php

namespace App\Actions\Communities;

use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ValidateCommunityDeletion
{
    /**
     * Validate that the community can be deleted by the given user.
     *
     * @throws AuthorizationException
     */
    public function validate(User $user, Community $community): void
    {
        Log::info('Verifying community deletion authorization', ['community' => $community, 'user' => $user]);
        Gate::forUser($user)->authorize('delete', $community);
        Log::info('Community deletion authorized', ['community' => $community, 'user' => $user]);
    }
}
