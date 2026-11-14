<?php

namespace App\Http\Controllers\API;

use App\Contracts\CourseMaterials\CreatesCourseMaterials;
use App\Contracts\CourseMaterials\DeletesCourseMaterials;
use App\Contracts\CourseMaterials\ReordersCourseMaterials;
use App\Contracts\CourseMaterials\UpdatesCourseMaterials;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CourseMaterial;
use App\Models\Space;
use App\Support\CourseMaterials;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The supplementary materials of a course space (#1336, ADR 0037): files and links for the whole course, a section or
 * a lesson. Members who can read the space read and download them; the managers of the course change them. Changes
 * are delegated to the actions (ADR 0006).
 */
class CourseMaterialApiController extends Controller
{
    use ApiResponses;

    /**
     * List the materials of a course space, in their order. Each one names its section or lesson (`page_cuid`), or
     * none for a material of the whole course. With `page`: the materials of that section or lesson only.
     */
    #[QueryParameter('page', description: 'The cuid of a section or a lesson: only its materials', type: 'string', example: 'pgcuid1')]
    final public function index(Request $request, Community $community, Space $space): JsonResponse
    {
        Gate::authorize('view', $space);
        CourseMaterials::assertCourseSpace($space);

        $materials = $space->materials()->with('page');
        $pageCuid = $request->query('page');
        if (is_string($pageCuid) && $pageCuid !== '') {
            $materials->where('page_id', CourseMaterials::target($space, $pageCuid)?->id);
        }

        return self::success('Materials', null, $materials->get()->toResourceCollection());
    }

    /**
     * Add a material (managers of the course). A link: JSON with `kind`, `title` and `url`. A file: multipart/form-data
     * with `kind`, `title` and `file`. `page` puts it on a section or a lesson; without it, on the whole course.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('kind', description: 'file or link', required: true, type: 'string', example: 'link')]
    #[BodyParameter('title', description: 'Title that members see (1 to 120 characters)', required: true, type: 'string', example: 'Cheat sheet')]
    #[BodyParameter('page', description: 'The cuid of a section or a lesson of the course. Leave out for the whole course', required: false, type: 'string')]
    #[BodyParameter('url', description: 'A link: an http or https address', required: false, type: 'string', format: 'uri', example: 'https://example.com/template')]
    #[BodyParameter('file', description: 'A file (multipart/form-data): PDF, ZIP, EPUB, Markdown, text, CSV, JSON, Office and OpenDocument files, PNG, JPEG or WebP, up to the size limit of the instance (50 MB by default)', required: false, type: 'file')]
    final public function store(Request $request, Community $community, Space $space): JsonResponse
    {
        $material = app(CreatesCourseMaterials::class)->create($request->user() ?? throw new AuthenticationException, $space, [
            ...$request->only(['kind', 'title', 'page', 'url']),
            'file' => $request->file('file'),
        ]);

        return self::created($material->load('page')->toResource(), 'Material added');
    }

    /**
     * Change the title of a material, or the address of a link (managers of the course).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('title', description: 'Title that members see (1 to 120 characters)', required: false, type: 'string')]
    #[BodyParameter('url', description: 'Links only: an http or https address', required: false, type: 'string', format: 'uri')]
    final public function update(Request $request, Community $community, Space $space, CourseMaterial $material): JsonResponse
    {
        $material = app(UpdatesCourseMaterials::class)->update($request->user() ?? throw new AuthenticationException, $material, $request->only(['title', 'url']));

        return self::success('Material updated', null, $material->load('page')->toResource());
    }

    /**
     * Delete a material and its file (managers of the course).
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, Space $space, CourseMaterial $material): Response
    {
        app(DeletesCourseMaterials::class)->delete($request->user() ?? throw new AuthenticationException, $material);

        return self::deleted();
    }

    /**
     * Put the materials of the course, of a section or of a lesson in a new order (managers of the course).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('page', description: 'The cuid of the section or the lesson. Leave out for the materials of the whole course', required: false, type: 'string')]
    #[BodyParameter('materials', description: 'The cuids of every material of that course, section or lesson, in the new order', required: true, type: 'array', example: ['mtcuid1', 'mtcuid2'])]
    final public function reorder(Request $request, Community $community, Space $space): JsonResponse
    {
        app(ReordersCourseMaterials::class)->reorder($request->user() ?? throw new AuthenticationException, $space, $request->only(['page', 'materials']));

        return self::success('Materials reordered', null, $space->materials()->with('page')->get()->toResourceCollection());
    }

    /**
     * Download the file of a material: members who can read the space. The answer is the file as an attachment, or a
     * redirect to a temporary address of the storage service (5 minutes). A link has no file: 404.
     */
    final public function download(Community $community, Space $space, CourseMaterial $material): SymfonyResponse
    {
        Gate::authorize('view', $space);

        if (! $material->isFile() || $material->disk === null || $material->path === null) {
            abort(404);
        }

        $disk = Storage::disk($material->disk);
        $fileName = $material->file_name ?? 'file';
        // Never shown inside the Knowii origin: always an attachment, and the browser must not guess another type
        $headers = [
            'Content-Type' => $material->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        if (config('filesystems.disks.'.$material->disk.'.driver') !== 'local') {
            return redirect()->away($disk->temporaryUrl($material->path, now()->addMinutes(5), [
                'ResponseContentDisposition' => 'attachment; filename="'.addcslashes($fileName, '"\\').'"',
                'ResponseContentType' => $headers['Content-Type'],
            ]));
        }

        if (! $disk->exists($material->path)) {
            abort(404);
        }

        return $disk->download($material->path, $fileName, $headers);
    }
}
