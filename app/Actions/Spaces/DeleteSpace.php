<?php

namespace App\Actions\Spaces;

use App\Contracts\Spaces\DeletesSpaces;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Space;
use App\Models\User;
use App\Support\ContentFiles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class DeleteSpace implements DeletesSpaces
{
    /**
     * Delete a space and everything in it (the content tables cascade on the space).
     */
    final public function delete(User $user, Space $space): void
    {
        Log::info('Processing request to delete a space', ['space' => $space->cuid]);

        $community = $space->community;

        Gate::forUser($user)->authorize('manageSpaces', $community);
        // A group space stays out of reach of the managers who cannot read it (#1325, ADR 0025)
        Gate::forUser($user)->authorize('view', $space);

        // Read before the rows go: the images of the resources that no community uses any more are deleted after (#1265)
        $files = ContentFiles::ofSpace($space);
        $space->delete();
        $files->deleteAfterCommit();

        SpaceTreeChanged::dispatch($community->cuid);
    }
}
