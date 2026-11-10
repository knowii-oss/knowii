<?php

namespace App\Actions\Resources;

use App\Contracts\Resources\DeletesCommunityResources;
use App\Events\CommunityResources\CommunityResourceDeleted;
use App\Models\CommunityResource;
use App\Models\User;
use App\Support\ContentFiles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * The curator, a member who manages the spaces, or a moderator of the space deletes a resource (#324). The database
 * deletes its comments, reactions and captures by cascade; the files go after the commit: the captured article
 * images, and the shared page with its cover and icon when no other community uses it (#1265).
 */
class DeleteCommunityResource implements DeletesCommunityResources
{
    final public function delete(User $user, CommunityResource $resource): void
    {
        Gate::forUser($user)->authorize('delete', $resource);

        // Read before the rows go: the event names the space, the cleanup reads the shared page
        $resource->loadMissing(['community', 'space']);
        $event = new CommunityResourceDeleted($resource);
        $files = ContentFiles::ofCommunityResource($resource);

        $resource->delete();
        $files->deleteAfterCommit();
        event($event);

        Log::info('Resource deleted', ['resource' => $resource->cuid, 'by' => $user->cuid]);
    }
}
