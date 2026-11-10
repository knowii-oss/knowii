<?php

namespace App\Console\Commands;

use App\Models\Upload;
use App\Support\Retention;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the uploaded images that no post or comment uses (#1201): the member cancelled the post, removed the image
 * from the text, or deleted the post. Uploads younger than retention.unused_uploads_days (7 days, #1273) stay: they can be in a post that a member
 * keeps open for days before saving it.
 */
#[Signature('uploads:prune {--hours= : Keep the uploads younger than this (default: retention.unused_uploads_days)} {--dry-run : List the uploads without deleting them}')]
#[Description('Delete the uploaded images that no post or comment uses')]
class PruneUnusedUploads extends Command
{
    public function handle(): int
    {
        $option = $this->option('hours');
        $hours = max(1, is_numeric($option) ? (int) $option : Retention::unusedUploadsDays() * 24);
        $dryRun = (bool) $this->option('dry-run');
        $count = 0;

        Upload::query()
            ->whereDoesntHave('usages')
            ->where('created_at', '<', now()->subHours($hours))
            ->orderBy('id')
            ->chunkById(200, function ($uploads) use ($dryRun, &$count) {
                foreach ($uploads as $upload) {
                    /** @var Upload $upload */
                    $count++;
                    if ($dryRun) {
                        $this->line("Would delete {$upload->path}");

                        continue;
                    }
                    Storage::disk($upload->disk)->delete($upload->path);
                    $upload->delete();
                    Log::info('Unused upload deleted', ['upload' => $upload->cuid, 'path' => $upload->path]);
                }
            });

        $this->info(($dryRun ? 'Unused uploads found: ' : 'Unused uploads deleted: ').$count);

        return self::SUCCESS;
    }
}
