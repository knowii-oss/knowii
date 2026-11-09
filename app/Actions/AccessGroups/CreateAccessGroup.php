<?php

namespace App\Actions\AccessGroups;

use App\Constants;
use App\Contracts\AccessGroups\CreatesAccessGroups;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Exceptions\BusinessException;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CreateAccessGroup implements CreatesAccessGroups
{
    /**
     * Create an access group (#1241, ADR 0025): the owner and the admins.
     */
    final public function create(User $user, Community $community, array $input): AccessGroup
    {
        Gate::forUser($user)->authorize('manageAccessGroups', $community);

        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : ($input['name'] ?? null);
        /** @var array{name: string, description?: string|null} $validated */
        $validated = Validator::make($input, AccessGroupInputRules::editable($community, true))->validate();

        if ($community->accessGroups()->count() >= Constants::MAX_ACCESS_GROUPS_PER_COMMUNITY) {
            throw new BusinessException('A community can have at most '.Constants::MAX_ACCESS_GROUPS_PER_COMMUNITY.' access groups');
        }

        $group = $community->accessGroups()->create([
            'name' => $validated['name'],
            'description' => AccessGroupInputRules::blankToNull($validated['description'] ?? null),
        ]);

        Log::info('Access group created', ['community' => $community->cuid, 'group' => $group->cuid]);
        AccessGroupsChanged::dispatch($community->cuid);

        return $group;
    }
}
