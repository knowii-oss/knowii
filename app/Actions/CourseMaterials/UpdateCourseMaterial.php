<?php

namespace App\Actions\CourseMaterials;

use App\Constants;
use App\Contracts\CourseMaterials\UpdatesCourseMaterials;
use App\Models\CourseMaterial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Change the title of a course material, or the address of a link (#1336). A file is never replaced: delete the
 * material and add it again.
 */
class UpdateCourseMaterial implements UpdatesCourseMaterials
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    final public function update(User $user, CourseMaterial $material, array $input): CourseMaterial
    {
        Gate::forUser($user)->authorize('managePages', $material->space);

        /** @var array{title?: string, url?: string} $validated */
        $validated = Validator::make($input, [
            'title' => ['sometimes', 'required', 'string', 'min:'.Constants::MIN_LENGTH_COURSE_MATERIAL_TITLE, 'max:'.Constants::MAX_LENGTH_COURSE_MATERIAL_TITLE],
            'url' => ['sometimes', 'required', 'string', 'url:http,https', 'max:'.Constants::MAX_LENGTH_SPACE_URL],
        ])->validate();

        if (array_key_exists('url', $validated) && $material->isFile()) {
            throw ValidationException::withMessages(['url' => 'Only a link has an address.']);
        }

        if (array_key_exists('title', $validated)) {
            $material->title = trim($validated['title']);
        }
        if (array_key_exists('url', $validated)) {
            $material->url = trim($validated['url']);
        }
        $material->save();

        Log::info('Course material updated', ['material' => $material->cuid]);

        return $material;
    }
}
