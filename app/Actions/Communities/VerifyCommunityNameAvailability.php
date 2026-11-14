<?php

namespace App\Actions\Communities;

use App\Constants;
use App\Contracts\Communities\VerifiesCommunityNameAvailability;
use App\Models\Community;
use App\Models\User;
use App\Rules\AvailableCommunityName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VerifyCommunityNameAvailability implements VerifiesCommunityNameAvailability
{
    /**
     * The answer is a boolean only: a taken name never tells which community took it, or whether it is private.
     * Only the owner of a community may check a new name for it (renaming is theirs, CommunityPolicy::update).
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException<Community>
     * @throws ValidationException
     */
    final public function verify(User $user, array $input): bool
    {
        // WARNING: the same rules as CreateCommunity.php, UpdateCommunity.php and newCommunitySchema (community.schema.ts)
        /** @var array{name: string, community?: string|null} $validated */
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'min:'.Constants::MIN_LENGTH_COMMUNITY_NAME, 'max:'.Constants::MAX_LENGTH_COMMUNITY_NAME, 'regex:'.Constants::ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX],
            'community' => ['sometimes', 'nullable', 'string'],
        ])->validate();

        $except = null;
        if (isset($validated['community'])) {
            $except = Community::query()->where('cuid', $validated['community'])->firstOrFail();
            Gate::forUser($user)->authorize('update', $except);
        }

        return AvailableCommunityName::isAvailable($validated['name'], $except, $user);
    }
}
