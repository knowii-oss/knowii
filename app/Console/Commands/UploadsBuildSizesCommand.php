<?php

namespace App\Console\Commands;

use App\Contracts\Uploads\BuildsUploadSizes;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Builds the smaller versions of the uploads that were stored before them (#1207, ADR 0040). Run it once after the
 * migration, and again when an upload was skipped.
 */
#[Signature('uploads:build-sizes {--dry-run : List what would be built without writing anything}')]
#[Description('Build the smaller versions of the uploaded images that have none yet')]
class UploadsBuildSizesCommand extends Command
{
    public function handle(BuildsUploadSizes $builder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = $builder->build($dryRun, fn (string $line) => $this->line($line));

        $this->table(['Uploads', $dryRun ? 'To do' : 'Done'], [
            ['With smaller versions', $counts['built']],
            ['Too small for any', $counts['small']],
            ['Skipped (missing or unreadable file)', $counts['skipped']],
        ]);

        return self::SUCCESS;
    }
}
