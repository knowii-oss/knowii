<?php

namespace App\Actions\CourseMaterials;

use App\Contracts\CourseMaterials\DeletesCourseMaterials;
use App\Models\CourseMaterial;
use App\Models\User;
use App\Support\ContentFiles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Delete a course material and its file (#1336, data rule 8).
 */
class DeleteCourseMaterial implements DeletesCourseMaterials
{
    /**
     * @throws AuthorizationException
     */
    final public function delete(User $user, CourseMaterial $material): void
    {
        Gate::forUser($user)->authorize('managePages', $material->space);

        // Read before the row goes: the file is deleted after
        $files = ContentFiles::ofCourseMaterial($material);
        $material->delete();
        $files->deleteAfterCommit();

        Log::info('Course material deleted', ['material' => $material->cuid]);
    }
}
