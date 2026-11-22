<?php

namespace App\Support\InvitationImports;

use App\Enums\KnowiiInvitationImportRefusal;

/**
 * One data record of a file of invitations (#1390, ADR 0061), as InvitationCsv reads it: its number in the file (the
 * header is 1) and its trimmed cells. The import of another tool (#1396) builds these records from its own mapping.
 * The name of the person is not here: it never leaves the reader of the file.
 */
final readonly class InvitationCsvRecord
{
    /**
     * @param  KnowiiInvitationImportRefusal|null  $problem  A problem of the record itself: `extra_cells` or `cell_too_long`
     */
    public function __construct(
        public int $rowNumber,
        public string $email,
        public string $role = '',
        public string $accessGroups = '',
        public string $tags = '',
        public ?KnowiiInvitationImportRefusal $problem = null,
    ) {}
}
