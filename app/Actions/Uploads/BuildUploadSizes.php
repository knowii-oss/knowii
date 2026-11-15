<?php

namespace App\Actions\Uploads;

use App\Contracts\Uploads\BuildsUploadSizes;
use App\Models\Upload;
use App\Support\UploadSizes;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Builds the smaller versions of the uploads of before #1207 (ADR 0040), in chunks. Safe to run again: it only reads
 * the uploads whose `sizes` is null, and it writes the list of widths after the files.
 */
final class BuildUploadSizes implements BuildsUploadSizes
{
    private const int CHUNK = 100;

    final public function build(bool $dryRun = false, ?Closure $report = null): array
    {
        $counts = ['built' => 0, 'small' => 0, 'skipped' => 0];
        $report ??= static function (string $line): void {};

        Upload::query()
            ->whereNull('sizes')
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($uploads) use ($dryRun, $report, &$counts) {
                foreach ($uploads as $upload) {
                    /** @var Upload $upload */
                    $counts[$this->buildFor($upload, $dryRun, $report)]++;
                }
            });

        return $counts;
    }

    /**
     * @param  Closure(string): void  $report
     * @return 'built'|'small'|'skipped'
     */
    private function buildFor(Upload $upload, bool $dryRun, Closure $report): string
    {
        try {
            $disk = Storage::disk($upload->disk);
            $contents = $disk->exists($upload->path) ? $disk->get($upload->path) : null;
        } catch (Throwable $e) {
            $contents = null;
            Log::warning('Upload sizes: file not readable', ['upload' => $upload->cuid, 'error' => $e->getMessage()]);
        }

        $info = is_string($contents) ? @getimagesizefromstring($contents) : false;
        if (! is_string($contents) || $info === false) {
            $report("Skipped {$upload->path}: the file is missing or cannot be read");

            return 'skipped';
        }

        // The real width of the file decides, not the one in the database
        $widths = UploadSizes::widthsFor($info[0]);
        if ($widths === []) {
            if (! $dryRun) {
                $upload->update(['sizes' => []]);
            }

            return 'small';
        }

        if ($dryRun) {
            $report('Would build '.implode(', ', $widths)." for {$upload->path}");

            return 'built';
        }

        try {
            $stored = UploadSizes::store($upload->disk, $upload->path, $contents, $info[0]);
        } catch (Throwable $e) {
            Log::warning('Upload sizes: cannot write', ['upload' => $upload->cuid, 'error' => $e->getMessage()]);
            $stored = [];
        }

        if ($stored === []) {
            $report("Skipped {$upload->path}: the versions could not be built");

            return 'skipped';
        }

        $upload->update(['sizes' => $stored]);

        return 'built';
    }
}
