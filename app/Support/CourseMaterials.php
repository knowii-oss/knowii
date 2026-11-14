<?php

namespace App\Support;

use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Models\CourseMaterial;
use App\Models\Page;
use App\Models\Space;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * The rules that the course material actions share (#1336, ADR 0037): where the files go, which files are accepted,
 * and which lesson or section a material belongs to.
 */
final class CourseMaterials
{
    /**
     * Documents, not media and nothing that a browser runs: the type is detected from the content of the file.
     * WARNING: aligned with COURSE_MATERIAL_EXTENSIONS (constants.ts)
     */
    public const array MIME_TYPES = [
        'application/pdf',
        'application/zip',
        'application/x-zip-compressed',
        'application/epub+zip',
        'application/json',
        'text/plain',
        'text/markdown',
        'text/csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    public const array EXTENSIONS = ['pdf', 'zip', 'epub', 'json', 'txt', 'md', 'csv', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'png', 'jpg', 'jpeg', 'webp'];

    /**
     * The private disk of the material files
     */
    public static function disk(): string
    {
        $disk = config('courses.materials_disk');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    public static function maxKilobytes(): int
    {
        $megabytes = config('courses.materials_max_megabytes');

        return max(1, is_numeric($megabytes) ? (int) $megabytes : 50) * 1024;
    }

    /**
     * @throws BusinessException
     */
    public static function assertCourseSpace(Space $space): void
    {
        if ($space->type !== KnowiiSpaceType::Course) {
            throw new BusinessException('Materials belong to course spaces');
        }
    }

    /**
     * The section or the lesson whose cuid is given, in this space; null for the whole course.
     *
     * @throws ValidationException
     */
    public static function target(Space $space, ?string $pageCuid): ?Page
    {
        if ($pageCuid === null || $pageCuid === '') {
            return null;
        }

        return $space->pages()->where('cuid', $pageCuid)->first()
            ?? throw ValidationException::withMessages(['page' => 'This lesson or section is not in this course.']);
    }

    /**
     * The materials of one target: the whole course (no page), a section or a lesson.
     *
     * @return Builder<CourseMaterial>
     */
    public static function of(Space $space, ?Page $page): Builder
    {
        $query = CourseMaterial::query()->where('space_id', $space->id);

        return $page === null ? $query->whereNull('page_id') : $query->where('page_id', $page->id);
    }

    /**
     * The name that members see and download: no path, no control characters.
     */
    public static function displayName(string $original): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/u', '', basename(str_replace('\\', '/', $original))));

        return $name === '' ? 'file' : mb_substr($name, 0, 200);
    }
}
