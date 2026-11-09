<?php

namespace App\Contracts\Resources;

use App\Exceptions\CaptureFailedException;
use App\Models\CommunityResource;
use Throwable;

interface CapturesResourcePages
{
    /**
     * Fetch the page of a community resource, update the metadata of its resource and store its readable content
     * (ADR 0018). The caller handles the capture status.
     *
     * @throws CaptureFailedException When another try cannot help (the reason is safe to show)
     * @throws Throwable When another try can help (network errors, server errors)
     */
    public function capture(CommunityResource $communityResource): void;
}
