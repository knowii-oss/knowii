<?php

namespace App\Jobs;

use App\Constants;
use App\Contracts\Resources\CapturesResourcePages;
use App\Enums\KnowiiCaptureStatus;
use App\Exceptions\CaptureFailedException;
use App\Models\CommunityResource;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Capture the page of a community resource (ADR 0018), on the default queue. Each change of the capture status saves
 * the community resource, which broadcasts CommunityResourceUpdated on the channel of its space.
 */
class CaptureCommunityResource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Seconds before the second and the third try
     *
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * A queued capture blocks another one of the same community resource for this long
     */
    public int $uniqueFor = 900;

    /**
     * @param  bool  $nameFromPage  The member gave no name: the title of the page becomes the name (#1062)
     */
    public function __construct(public CommunityResource $communityResource, public bool $nameFromPage = false) {}

    public function uniqueId(): string
    {
        return (string) $this->communityResource->id;
    }

    public function handle(CapturesResourcePages $capturer): void
    {
        $communityResource = $this->communityResource;
        $communityResource->update(['capture_status' => KnowiiCaptureStatus::Capturing, 'capture_failure_reason' => null]);

        try {
            $capturer->capture($communityResource);
        } catch (CaptureFailedException $e) {
            // Another try cannot help
            $this->markFailed($e->getMessage());

            return;
        }

        $changes = ['capture_status' => KnowiiCaptureStatus::Captured, 'capture_failure_reason' => null];
        $name = $communityResource->resource()->value('name');
        $title = is_string($name) ? trim($name) : '';
        if ($this->nameFromPage && $title !== '') {
            $changes['name'] = Str::limit($title, Constants::MAX_LENGTH_COMMUNITY_RESOURCE_NAME - 1, '…');
        }
        $communityResource->update($changes);

        Log::info('Resource captured', ['resource' => $communityResource->cuid]);
    }

    /**
     * After the last try
     */
    public function failed(?Throwable $exception): void
    {
        Log::warning('Resource capture failed', ['resource' => $this->communityResource->cuid, 'reason' => $exception?->getMessage()]);
        $this->markFailed('The page could not be loaded. Try again later.');
    }

    private function markFailed(string $reason): void
    {
        $this->communityResource->refresh()->update([
            'capture_status' => KnowiiCaptureStatus::Failed,
            'capture_failure_reason' => Str::limit($reason, 250),
        ]);
    }
}
