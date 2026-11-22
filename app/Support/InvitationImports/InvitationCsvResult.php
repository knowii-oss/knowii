<?php

namespace App\Support\InvitationImports;

/**
 * What InvitationCsv read from a file: one error for the whole file, or the ignored columns and the records.
 */
final readonly class InvitationCsvResult
{
    /**
     * @param  list<string>  $ignoredColumns  The headers of the columns that the import does not use, as the file wrote them
     * @param  list<InvitationCsvRecord>  $records
     */
    private function __construct(
        public ?string $error,
        public array $ignoredColumns,
        public array $records,
    ) {}

    public static function failed(string $error): self
    {
        return new self($error, [], []);
    }

    /**
     * @param  list<string>  $ignoredColumns
     * @param  list<InvitationCsvRecord>  $records
     */
    public static function read(array $ignoredColumns, array $records): self
    {
        return new self(null, $ignoredColumns, $records);
    }
}
