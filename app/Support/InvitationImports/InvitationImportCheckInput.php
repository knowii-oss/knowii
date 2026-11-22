<?php

namespace App\Support\InvitationImports;

use App\Enums\KnowiiInvitationImportRefusal;
use App\Models\InvitationImportRow;

/**
 * One row to check (#1390, ADR 0061): from a record of a file at the upload, from a stored row at the confirm.
 */
final readonly class InvitationImportCheckInput
{
    /**
     * @param  string  $role  The cell: empty for the default role
     * @param  list<string>  $accessGroupNames  The names of the row itself, cleaned
     * @param  list<string>  $memberTagNames  The names of the row itself, cleaned
     */
    public function __construct(
        public int $rowNumber,
        public string $email,
        public string $role,
        public array $accessGroupNames,
        public array $memberTagNames,
        public ?KnowiiInvitationImportRefusal $problem = null,
    ) {}

    public static function fromRecord(InvitationCsvRecord $record): self
    {
        return new self(
            $record->rowNumber,
            $record->email,
            $record->role,
            InvitationImportChecks::splitNames($record->accessGroups),
            InvitationImportChecks::splitNames($record->tags),
            $record->problem,
        );
    }

    /**
     * A row of a preview, checked again: its names hold the defaults already
     */
    public static function fromRow(InvitationImportRow $row): self
    {
        return new self($row->row_number, $row->email, $row->role->value ?? '', $row->access_group_names, $row->member_tag_names);
    }
}
