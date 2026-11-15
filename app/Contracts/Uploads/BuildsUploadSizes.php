<?php

namespace App\Contracts\Uploads;

use Closure;

interface BuildsUploadSizes
{
    /**
     * Builds the smaller versions of the uploads that have none yet (`sizes` is null, #1207, ADR 0040). A file that is
     * missing or cannot be read is reported and skipped: nothing is deleted.
     *
     * With $dryRun nothing is written, and the counts are what a real run would do.
     *
     * @param  (Closure(string): void)|null  $report  Called with one line for each upload that is skipped (or listed, with $dryRun)
     * @return array{built: int, small: int, skipped: int} The uploads that got versions, the uploads too small for any (their list stays empty), and the uploads skipped
     */
    public function build(bool $dryRun = false, ?Closure $report = null): array;
}
