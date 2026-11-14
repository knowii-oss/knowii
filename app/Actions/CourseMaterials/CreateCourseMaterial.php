<?php

namespace App\Actions\CourseMaterials;

use App\Constants;
use App\Contracts\CourseMaterials\CreatesCourseMaterials;
use App\Enums\KnowiiCourseMaterialKind;
use App\Exceptions\BusinessException;
use App\Exceptions\TechnicalException;
use App\Models\CourseMaterial;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use App\Support\CourseMaterials;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Visus\Cuid2\Cuid2;

/**
 * Add a file or a link to a course, a section or a lesson (#1336, ADR 0037): the managers of the course (the owner,
 * the admins and the moderators who can read the space).
 */
class CreateCourseMaterial implements CreatesCourseMaterials
{
    /**
     * @throws AuthorizationException
     * @throws BusinessException
     * @throws TechnicalException
     * @throws ValidationException
     */
    final public function create(User $user, Space $space, array $input): CourseMaterial
    {
        Gate::forUser($user)->authorize('managePages', $space);
        CourseMaterials::assertCourseSpace($space);

        /** @var array{kind: string} $kindInput */
        $kindInput = Validator::make($input, ['kind' => ['required', Rule::enum(KnowiiCourseMaterialKind::class)]])->validate();
        $kind = KnowiiCourseMaterialKind::from($kindInput['kind']);

        // WARNING: aligned with courseMaterialFormSchema (course-material.schema.ts)
        $rules = [
            'title' => ['required', 'string', 'min:'.Constants::MIN_LENGTH_COURSE_MATERIAL_TITLE, 'max:'.Constants::MAX_LENGTH_COURSE_MATERIAL_TITLE],
            'page' => ['sometimes', 'nullable', 'string'],
        ];
        if ($kind === KnowiiCourseMaterialKind::Link) {
            $rules['url'] = ['required', 'string', 'url:http,https', 'max:'.Constants::MAX_LENGTH_SPACE_URL];
        } else {
            // The type comes from the content of the file, and the extension must be on the list too
            $rules['file'] = ['required', 'file', 'max:'.CourseMaterials::maxKilobytes(), 'mimetypes:'.implode(',', CourseMaterials::MIME_TYPES), 'extensions:'.implode(',', CourseMaterials::EXTENSIONS)];
        }

        /** @var array{title: string, page?: string|null, url?: string, file?: UploadedFile} $validated */
        $validated = Validator::make($input, $rules)->validate();

        $page = CourseMaterials::target($space, $validated['page'] ?? null);
        if (CourseMaterials::of($space, $page)->count() >= Constants::MAX_COURSE_MATERIALS_PER_TARGET) {
            throw new BusinessException('A course, a section or a lesson can have at most '.Constants::MAX_COURSE_MATERIALS_PER_TARGET.' materials');
        }

        $cuid = (new Cuid2)->toString();
        $attributes = [
            'cuid' => $cuid,
            'community_id' => $space->community_id,
            'space_id' => $space->id,
            'page_id' => $page?->id,
            'title' => trim($validated['title']),
            'kind' => $kind,
        ];

        if ($kind === KnowiiCourseMaterialKind::Link) {
            $attributes['url'] = trim((string) ($validated['url'] ?? ''));
        } else {
            $file = $validated['file'] ?? throw ValidationException::withMessages(['file' => 'The file is required.']);
            $attributes = [...$attributes, ...$this->store($file, $space, $cuid)];
        }

        $material = DB::transaction(static fn () => CourseMaterial::create([
            ...$attributes,
            'position' => self::nextPosition($space, $page),
        ]));

        Log::info('Course material added', ['material' => $material->cuid, 'space' => $space->cuid, 'kind' => $kind->value]);

        return $material;
    }

    /**
     * The position after the last material of the course, the section or the lesson (0 when there is none).
     */
    private static function nextPosition(Space $space, ?Page $page): int
    {
        $max = CourseMaterials::of($space, $page)->max('position');

        return is_numeric($max) ? ((int) $max) + 1 : 0;
    }

    /**
     * Put the file on the private disk, under a folder that only this material uses.
     *
     * @return array{disk: string, path: string, file_name: string, mime_type: string, size_bytes: int}
     *
     * @throws TechnicalException
     */
    private function store(UploadedFile $file, Space $space, string $cuid): array
    {
        $disk = CourseMaterials::disk();
        $displayName = CourseMaterials::displayName($file->getClientOriginalName());
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = (Str::slug(pathinfo($displayName, PATHINFO_FILENAME)) ?: 'file').'.'.$extension;
        $directory = sprintf('course-materials/%s/%s', $space->community->cuid, $cuid);

        $path = Storage::disk($disk)->putFileAs($directory, $file, $storedName, ['visibility' => 'private']);
        if (! is_string($path)) {
            throw new TechnicalException('Failed to store the file');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'file_name' => $displayName,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => (int) $file->getSize(),
        ];
    }
}
