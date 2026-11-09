<?php

namespace App\Actions\Spaces;

use App\Constants;
use App\Contracts\Spaces\CreatesSpaces;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\Positions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateSpace implements CreatesSpaces
{
    /**
     * Create a space at the end of a space group of the community.
     */
    final public function create(User $user, Community $community, array $input): Space
    {
        Log::info('Processing request to create a space', ['community' => $community->cuid]);

        Gate::forUser($user)->authorize('manageSpaces', $community);

        /** @var array{name: string, description?: string|null, emoji?: string|null, type: string, visibility: string, posting_policy: string, space_group_cuid: string} $validated */
        $validated = Validator::make($input, SpaceInputRules::create())->validate();

        $spaceGroup = self::findSpaceGroup($community, $validated['space_group_cuid']);

        if ($community->spaces()->count() >= Constants::MAX_SPACES_PER_COMMUNITY) {
            throw new BusinessException('A community can have at most '.Constants::MAX_SPACES_PER_COMMUNITY.' spaces');
        }

        $space = DB::transaction(function () use ($community, $spaceGroup, $validated) {
            $position = Positions::next($spaceGroup->spaces());

            return Space::create([
                'community_id' => $community->id,
                'space_group_id' => $spaceGroup->id,
                'name' => trim($validated['name']),
                'description' => self::blankToNull($validated['description'] ?? null),
                'emoji' => self::blankToNull($validated['emoji'] ?? null),
                'type' => $validated['type'],
                'visibility' => $validated['visibility'],
                'posting_policy' => $validated['posting_policy'],
                'position' => $position,
            ]);
        });

        SpaceTreeChanged::dispatch($community->cuid);

        Log::info('Space created', ['space' => $space->cuid]);

        return $space;
    }

    /**
     * The space group must belong to the same community (never trust a cuid from the client).
     */
    public static function findSpaceGroup(Community $community, string $spaceGroupCuid): SpaceGroup
    {
        $spaceGroup = $community->spaceGroups()->where('cuid', $spaceGroupCuid)->first();

        if ($spaceGroup === null) {
            throw ValidationException::withMessages([
                'space_group_cuid' => 'The space group does not exist in this community',
            ]);
        }

        return $spaceGroup;
    }

    public static function blankToNull(?string $value): ?string
    {
        return blank($value) ? null : trim((string) $value);
    }
}
