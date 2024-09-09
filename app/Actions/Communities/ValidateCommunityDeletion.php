<?php

namespace App\Actions\Communities;

use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ValidateCommunityDeletion
{
    /**
     * Validate that the community can be deleted by the given user.
     *
     * @param User $user
     * @param  Community $community
     * @return void
     */
    public function validate(User $user, Community $community): void
    {
        Gate::forUser($user)->authorize('delete', $community);

        if ($community->personal_community) {
            throw ValidationException::withMessages([
                'community' => __('You may not delete your personal space.'),
            ])->errorBag('deleteCommunity');
        }
    }
}
