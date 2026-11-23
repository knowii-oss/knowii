<?php

namespace App\Http\Controllers\API;

use App\Constants;
use App\Contracts\InvitationImports\ConfirmsInvitationImports;
use App\Contracts\InvitationImports\CreatesInvitationImports;
use App\Contracts\InvitationImports\DeletesInvitationImports;
use App\Contracts\InvitationImports\ResendsInvitationImports;
use App\Enums\KnowiiInvitationImportOutcome;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\InvitationImport;
use App\Support\InvitationImports\InvitationImports;
use App\Support\Pagination;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Invitations from a CSV file (#1390, ADR 0061): an upload gives a preview, its person sends it, the mails go out at the
 * pace of the mail provider, and the record says the result of each row. For the owner, the admins and the moderators
 * with the "manage members" right. Changes are delegated to the actions (ADR 0006).
 */
class InvitationImportApiController extends Controller
{
    use ApiResponses;

    /**
     * Upload a CSV file (UTF-8, a header row, a column `email`; optional `role`, `access_groups`, `tags`, names separated
     * by `|`), at most 1000 rows and 1 MB. The server reads the file one time and does not keep it: the answer is the
     * import in `preview`, with one row per data row and its result. A new upload deletes the older preview of the caller
     * in the community. The defaults apply to every row: a row adds its own groups and tags, and its role replaces the
     * default role.
     *
     * @requestMediaType multipart/form-data
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('file', description: 'The CSV file, UTF-8, at most 1 MB and 1000 rows', required: true, type: 'string', format: 'binary')]
    #[BodyParameter('default_role', description: 'admin, moderator or member (the default): lower than the role of the caller', required: false, type: 'string', example: 'member')]
    #[BodyParameter('access_groups', description: 'Cuids of access groups of the community that every row gets', required: false, type: 'array<string>')]
    #[BodyParameter('member_tags', description: 'Cuids of member tags of the community that every row gets', required: false, type: 'array<string>')]
    final public function store(Request $request, Community $community): JsonResponse
    {
        $file = $request->file('file');
        $import = app(CreatesInvitationImports::class)->fromUpload(
            $request->user() ?? throw new AuthenticationException,
            $community,
            $file instanceof UploadedFile ? $file : null,
            $request->only(['default_role', 'access_groups', 'member_tags']),
        );
        InvitationImports::prepare([$import], $community);

        return self::created($import->toResource(), 'Invitation import checked');
    }

    /**
     * The imports of the community, newest first, with their counts.
     */
    #[QueryParameter('limit', description: 'Number of imports (default 10, maximum 100)', type: 'int', example: 10)]
    #[QueryParameter('offset', description: 'Number of imports to skip', type: 'int', example: 0)]
    final public function index(Request $request, Community $community): JsonResponse
    {
        Gate::authorize('addCommunityMember', $community);

        $page = Pagination::fromRequest($request)->apply(InvitationImports::listed($community)->latest()->latest('id'));
        InvitationImports::prepare($page['items'], $community);

        return self::success('Invitation imports', $page['metadata'], $page['items']->toResourceCollection());
    }

    /**
     * One import with its counts.
     */
    final public function show(Community $community, InvitationImport $invitationImport): JsonResponse
    {
        Gate::authorize('addCommunityMember', $community);
        InvitationImports::assertAvailable($invitationImport);
        InvitationImports::prepare([$invitationImport], $community);

        return self::success('Invitation import', null, $invitationImport->toResource());
    }

    /**
     * The rows of an import, by their number in the file, with the email address, the result and the reason in words.
     */
    #[QueryParameter('outcome', description: 'Only these results, comma separated: valid, refused, queued, sent, held, failed, skipped', type: 'string', example: 'refused')]
    #[QueryParameter('limit', description: 'Number of rows (default 10, maximum 1000)', type: 'int', example: 50)]
    #[QueryParameter('offset', description: 'Number of rows to skip', type: 'int', example: 0)]
    final public function rows(Request $request, Community $community, InvitationImport $invitationImport): JsonResponse
    {
        Gate::authorize('addCommunityMember', $community);
        InvitationImports::assertAvailable($invitationImport);

        $outcome = $request->query('outcome');
        $outcomes = is_string($outcome) && trim($outcome) !== '' ? array_values(array_filter(array_map(trim(...), explode(',', $outcome)), static fn (string $item): bool => $item !== '')) : [];
        Validator::make(['outcome' => $outcomes], [
            'outcome' => ['array'],
            'outcome.*' => [Rule::in(KnowiiInvitationImportOutcome::toStringArray())],
        ])->validate();

        $query = $invitationImport->rows()->orderBy('row_number')->orderBy('id');
        if ($outcomes !== []) {
            $query->whereIn('outcome', $outcomes);
        }
        $page = Pagination::fromRequest($request, Constants::PAGINATION_DEFAULT_LIMIT, Constants::MAX_INVITATION_IMPORT_ROWS)->apply($query);

        return self::success('Invitation import rows', $page['metadata'], $page['items']->toResourceCollection());
    }

    /**
     * Send a preview: only the person who uploaded it. Every valid row is checked again; a row that changed since the
     * preview is refused with its reason. The import is refused as a whole (422), with nothing changed, when the instance
     * holds its mail, cannot send so many mails this month, or when the rows pass a daily cap of the invitation mails
     * (of the community, of the caller over all their communities, or of the instance). 202: the mails go out one by
     * one.
     *
     * @throws AuthenticationException
     */
    final public function confirm(Request $request, Community $community, InvitationImport $invitationImport): JsonResponse
    {
        $import = app(ConfirmsInvitationImports::class)->confirm($request->user() ?? throw new AuthenticationException, $invitationImport);
        InvitationImports::prepare([$import], $community);

        /** @status 202 */
        return self::success('Invitation import sent', null, $import->toResource(), Response::HTTP_ACCEPTED);
    }

    /**
     * Send the mails of a set of rows again: `not_sent` (the held and the failed rows, and the rows queued for longer
     * than 2 hours 16 minutes, past the retries of a mail, at any time) or `not_joined` (the people who did not join,
     * one time, 3 days after the send at the earliest). Only rows whose invitation still exists.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('rows', description: 'not_sent or not_joined', required: true, type: 'string', example: 'not_sent')]
    final public function resend(Request $request, Community $community, InvitationImport $invitationImport): JsonResponse
    {
        $import = app(ResendsInvitationImports::class)->resend($request->user() ?? throw new AuthenticationException, $invitationImport, $request->only(['rows']));
        InvitationImports::prepare([$import], $community);

        /** @status 202 */
        return self::success('Invitation import sent again', null, $import->toResource(), Response::HTTP_ACCEPTED);
    }

    /**
     * Discard a preview (the person who uploaded it, the owner, the admins), or delete the record of a sent import early
     * (the owner and the admins): its invitations stay. A sent import whose mails still go out, or whose last mail went to
     * the queue less than 24 hours ago, stays (422 with the date).
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, InvitationImport $invitationImport): Response
    {
        app(DeletesInvitationImports::class)->delete($request->user() ?? throw new AuthenticationException, $invitationImport);

        return self::deleted();
    }
}
