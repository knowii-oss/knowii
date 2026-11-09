<?php

namespace App\Actions\Spaces;

use App\Contracts\Spaces\UpdatesSpaces;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Space;
use App\Models\User;
use App\Support\Positions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UpdateSpace implements UpdatesSpaces
{
    /**
     * Partial update of a space (PATCH). The type never changes: the content of a space depends on it.
     * Moving the space to another group puts it at the end of that group. The slug stays the same after a rename,
     * so links keep working.
     */
    final public function update(User $user, Space $space, array $input): Space
    {
        Log::info('Processing request to update a space', ['space' => $space->cuid]);

        $community = $space->community;

        Gate::forUser($user)->authorize('manageSpaces', $community);

        /** @var array{name?: string, description?: string|null, emoji?: string|null, visibility?: string, posting_policy?: string, space_group_cuid?: string} $validated */
        $validated = Validator::make($input, SpaceInputRules::editable(false))->validate();

        DB::transaction(function () use ($space, $community, $validated) {
            $changes = [];

            if (array_key_exists('name', $validated)) {
                $changes['name'] = trim($validated['name']);
            }

            foreach (['description', 'emoji'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $changes[$field] = CreateSpace::blankToNull($validated[$field]);
                }
            }

            foreach (['visibility', 'posting_policy'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $changes[$field] = $validated[$field];
                }
            }

            if (array_key_exists('space_group_cuid', $validated) && $validated['space_group_cuid'] !== $space->spaceGroup->cuid) {
                $targetGroup = CreateSpace::findSpaceGroup($community, $validated['space_group_cuid']);
                $changes['space_group_id'] = $targetGroup->id;
                $changes['position'] = Positions::next($targetGroup->spaces());
            }

            $space->update($changes);
        });

        SpaceTreeChanged::dispatch($community->cuid);

        return $space->refresh();
    }
}
