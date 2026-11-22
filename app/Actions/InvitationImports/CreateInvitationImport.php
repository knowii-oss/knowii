<?php

namespace App\Actions\InvitationImports;

use App\Constants;
use App\Contracts\InvitationImports\CreatesInvitationImports;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiInvitationImportOutcome;
use App\Enums\KnowiiInvitationImportState;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\InvitationImport;
use App\Models\User;
use App\Support\CommunityRoles;
use App\Support\InvitationGrants;
use App\Support\InvitationImports\InvitationCsv;
use App\Support\InvitationImports\InvitationImportCheckInput;
use App\Support\InvitationImports\InvitationImportChecks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Visus\Cuid2\Cuid2;

class CreateInvitationImport implements CreatesInvitationImports
{
    /**
     * The types that a CSV file comes with: a spreadsheet on Windows sends the type of its own files. The content rules of
     * InvitationCsv are the real check
     */
    public const array MIME_TYPES = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];

    /**
     * The rows go in with one insert per this many rows
     */
    private const int INSERT_CHUNK = 500;

    /**
     * Check the records and store them as a preview (#1390, ADR 0061): the gate `addCommunityMember` first, then the
     * defaults (the role with the role hierarchy, the groups and the tags through InvitationGrants: a refused default is a
     * 403 or a 400 for the whole upload), then each row (InvitationImportChecks). A new upload of the same person in the
     * same community deletes their older preview. The name of a row is never stored, and the log holds cuids and counts.
     *
     * @throws AuthorizationException
     * @throws BusinessException
     * @throws ValidationException
     */
    final public function create(User $user, Community $community, iterable $records, array $input): InvitationImport
    {
        Gate::forUser($user)->authorize('addCommunityMember', $community);
        if ($community->visibility === KnowiiCommunityVisibility::Personal) {
            throw new BusinessException('A personal community cannot invite anyone');
        }

        /** @var array{default_role?: string|null} $validated */
        $validated = Validator::make($input, [
            'default_role' => ['sometimes', 'nullable', 'string', Rule::in([KnowiiCommunityMemberRole::Admin->value, KnowiiCommunityMemberRole::Moderator->value, KnowiiCommunityMemberRole::Member->value])],
            'ignored_columns' => ['sometimes', 'array', 'max:'.Constants::MAX_INVITATION_IMPORT_COLUMNS],
            'ignored_columns.*' => ['string', 'max:64'],
        ])->validate();
        $defaultRole = KnowiiCommunityMemberRole::from($validated['default_role'] ?? KnowiiCommunityMemberRole::Member->value);
        if (! CommunityRoles::canGrant($user, $community, $defaultRole)) {
            throw new AuthorizationException('You cannot give this role');
        }
        ['access_groups' => $groups, 'member_tags' => $tags] = InvitationGrants::read($user, $community, $input);

        $inputs = [];
        foreach ($records as $record) {
            $inputs[] = InvitationImportCheckInput::fromRecord($record);
        }
        if (count($inputs) > Constants::MAX_INVITATION_IMPORT_ROWS) {
            throw ValidationException::withMessages(['file' => InvitationCsv::TOO_MANY_ROWS]);
        }
        $ignored = [];
        foreach (is_array($input['ignored_columns'] ?? null) ? $input['ignored_columns'] : [] as $column) {
            if (is_string($column)) {
                $ignored[] = $column;
            }
        }

        $checked = InvitationImportChecks::check($user, $community, $inputs, $defaultRole, $groups, $tags);

        $import = DB::transaction(static function () use ($user, $community, $defaultRole, $groups, $tags, $ignored, $checked): InvitationImport {
            // One preview per person and community: the older one goes, with its rows
            InvitationImport::query()
                ->where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->where('state', KnowiiInvitationImportState::Preview->value)
                ->delete();

            $import = InvitationImport::query()->create([
                'community_id' => $community->id,
                'user_id' => $user->id,
                'state' => KnowiiInvitationImportState::Preview,
                'default_role' => $defaultRole,
                'default_access_groups' => $groups->pluck('cuid')->values()->all(),
                'default_member_tags' => $tags->pluck('cuid')->values()->all(),
                'ignored_columns' => $ignored,
            ]);

            $now = now();
            foreach (array_chunk($checked, self::INSERT_CHUNK) as $chunk) {
                $rows = [];
                foreach ($chunk as $row) {
                    $rows[] = [
                        'cuid' => (new Cuid2)->toString(),
                        'invitation_import_id' => $import->id,
                        'row_number' => $row->rowNumber,
                        'email' => $row->email,
                        'role' => $row->role?->value,
                        'access_group_names' => json_encode($row->groupNames, JSON_THROW_ON_ERROR),
                        'member_tag_names' => json_encode($row->tagNames, JSON_THROW_ON_ERROR),
                        'outcome' => ($row->reason === null ? KnowiiInvitationImportOutcome::Valid : KnowiiInvitationImportOutcome::Refused)->value,
                        'reason' => $row->reason?->value,
                        'detail' => $row->detail === null ? null : mb_substr($row->detail, 0, 64),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('invitation_import_rows')->insert($rows);
            }

            return $import;
        });

        $refused = count(array_filter($checked, static fn ($row) => $row->reason !== null));
        Log::info('Invitation import checked', [
            'community' => $community->cuid,
            'import' => $import->cuid,
            'rows' => count($checked),
            'valid' => count($checked) - $refused,
            'refused' => $refused,
        ]);

        return $import;
    }

    /**
     * Gate first, then the rules of the file (size, extension, type), then its reading. An error of the file is a 400 on
     * `file`
     *
     * @throws AuthorizationException
     * @throws BusinessException
     * @throws ValidationException
     */
    final public function fromUpload(User $user, Community $community, ?UploadedFile $file, array $input): InvitationImport
    {
        Gate::forUser($user)->authorize('addCommunityMember', $community);

        Validator::make(['file' => $file], [
            'file' => ['required', 'file', 'max:'.Constants::MAX_INVITATION_IMPORT_KILOBYTES, 'extensions:csv', 'mimetypes:'.implode(',', self::MIME_TYPES)],
        ], ['file.max' => InvitationCsv::TOO_LARGE, 'file.mimetypes' => InvitationCsv::NOT_UTF8])->validate();

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => InvitationCsv::NOT_UTF8]);
        }
        $result = InvitationCsv::read((string) $file->getRealPath());
        if ($result->error !== null) {
            throw ValidationException::withMessages(['file' => $result->error]);
        }

        return $this->create($user, $community, $result->records, [
            'default_role' => $input['default_role'] ?? null,
            'access_groups' => $input['access_groups'] ?? null,
            'member_tags' => $input['member_tags'] ?? null,
            'ignored_columns' => $result->ignoredColumns,
        ]);
    }
}
