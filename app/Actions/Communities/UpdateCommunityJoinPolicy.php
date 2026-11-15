<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\UpdatesCommunityJoinPolicy;
use App\Enums\KnowiiCommunityJoinPolicy;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateCommunityJoinPolicy implements UpdatesCommunityJoinPolicy
{
    /**
     * Change who can join a shared community (PATCH): by invitation (the default) or open (#1486, ADR 0044). The owner
     * and the admins, like the rights of the moderators. Never for a personal community: nobody joins it.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    final public function update(User $user, Community $community, array $input): Community
    {
        Gate::forUser($user)->authorize('manageJoinPolicy', $community);

        /** @var array{join_policy: string} $validated */
        $validated = Validator::make($input, [
            'join_policy' => ['required', Rule::enum(KnowiiCommunityJoinPolicy::class)],
        ])->validate();

        $policy = KnowiiCommunityJoinPolicy::from($validated['join_policy']);

        if ($community->visibility === KnowiiCommunityVisibility::Personal) {
            throw ValidationException::withMessages(['join_policy' => 'Nobody joins a personal community']);
        }

        $community->update(['join_policy' => $policy]);

        Log::info('Community join policy updated', ['community' => $community->cuid, 'join_policy' => $policy->value]);

        return $community;
    }
}
