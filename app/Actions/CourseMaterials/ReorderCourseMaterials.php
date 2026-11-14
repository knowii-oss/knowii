<?php

namespace App\Actions\CourseMaterials;

use App\Contracts\CourseMaterials\ReordersCourseMaterials;
use App\Models\Space;
use App\Models\User;
use App\Support\CourseMaterials;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Put the materials of a course, a section or a lesson in a new order (#1336, data rule 3): every material of that
 * target, once.
 */
class ReorderCourseMaterials implements ReordersCourseMaterials
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    final public function reorder(User $user, Space $space, array $input): void
    {
        Gate::forUser($user)->authorize('managePages', $space);

        /** @var array{page?: string|null, materials: list<string>} $validated */
        $validated = Validator::make($input, [
            'page' => ['sometimes', 'nullable', 'string'],
            'materials' => ['required', 'array', 'list'],
            'materials.*' => ['required', 'string', 'distinct'],
        ])->validate();

        $page = CourseMaterials::target($space, $validated['page'] ?? null);
        $ids = CourseMaterials::of($space, $page)->pluck('id', 'cuid');
        $order = $validated['materials'];
        if (count($order) !== $ids->count() || array_diff($order, $ids->keys()->all()) !== []) {
            throw ValidationException::withMessages(['materials' => 'List every material of this course, section or lesson, once.']);
        }

        DB::transaction(static function () use ($order, $ids) {
            foreach ($order as $position => $cuid) {
                DB::table('course_materials')->where('id', $ids[$cuid])->update(['position' => $position, 'updated_at' => now()]);
            }
        });

        Log::info('Course materials reordered', ['space' => $space->cuid, 'page' => $page?->cuid]);
    }
}
