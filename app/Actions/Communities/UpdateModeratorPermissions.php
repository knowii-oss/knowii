<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\UpdatesModeratorPermissions;
use App\Enums\KnowiiModeratorPermission;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UpdateModeratorPermissions implements UpdatesModeratorPermissions
{
    /**
     * Switch some rights of the moderators of a community (PATCH): owner and admins (#1240, ADR 0017).
     */
    final public function update(User $user, Community $community, array $input): Community
    {
        Gate::forUser($user)->authorize('manageModeratorPermissions', $community);

        $rules = [];
        foreach (KnowiiModeratorPermission::cases() as $permission) {
            $rules[$permission->value] = ['sometimes', 'boolean'];
        }

        /** @var array<string, bool> $validated */
        $validated = Validator::make($input, $rules)->validate();

        $permissions = $community->moderatorPermissions();
        foreach ($validated as $key => $value) {
            $permissions[$key] = (bool) $value;
        }

        $community->update(['moderator_permissions' => $permissions]);

        Log::info('Moderator rights updated', ['community' => $community->cuid, 'permissions' => $permissions]);

        return $community;
    }
}
