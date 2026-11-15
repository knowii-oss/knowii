<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\UpdatesCommunityIndexing;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateCommunityIndexing implements UpdatesCommunityIndexing
{
    /**
     * Let search engines index the public spaces of a shared community, or not (PATCH, #1487, ADR 0044). Off by default.
     * The owner and the admins. Never for a personal community: it has no public space.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    final public function update(User $user, Community $community, array $input): Community
    {
        Gate::forUser($user)->authorize('manageIndexing', $community);

        /** @var array{indexing_enabled: bool} $validated */
        $validated = Validator::make($input, [
            'indexing_enabled' => ['required', 'boolean:strict'],
        ])->validate();

        if ($community->visibility === KnowiiCommunityVisibility::Personal) {
            throw ValidationException::withMessages(['indexing_enabled' => 'A personal community has no public space']);
        }

        $community->update(['indexing_enabled' => $validated['indexing_enabled']]);

        Log::info('Community indexing updated', ['community' => $community->cuid, 'indexing_enabled' => $validated['indexing_enabled']]);

        return $community;
    }
}
