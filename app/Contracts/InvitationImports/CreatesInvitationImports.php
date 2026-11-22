<?php

namespace App\Contracts\InvitationImports;

use App\Models\Community;
use App\Models\InvitationImport;
use App\Models\User;
use App\Support\InvitationImports\InvitationCsvRecord;
use Illuminate\Http\UploadedFile;

interface CreatesInvitationImports
{
    /**
     * Check the records of a file (or of another source, #1396) and store them as a preview of the person, with one row
     * per record and its result.
     *
     * @param  iterable<InvitationCsvRecord>  $records
     * @param  array<mixed>  $input  The defaults: `default_role`, `access_groups`, `member_tags` (cuids), `ignored_columns`
     */
    public function create(User $user, Community $community, iterable $records, array $input): InvitationImport;

    /**
     * Read an uploaded CSV file (InvitationCsv), then create() with its records. The file is read one time, from the
     * temporary file of the request, and never stored.
     *
     * @param  array<mixed>  $input  `default_role`, `access_groups`, `member_tags`
     */
    public function fromUpload(User $user, Community $community, ?UploadedFile $file, array $input): InvitationImport;
}
