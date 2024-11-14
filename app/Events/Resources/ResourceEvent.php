<?php

namespace App\Events\Resources;

use App\Models\Resource;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

abstract class ResourceEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * The resource instance.
     */
    public Resource $resource;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Resource $resource)
    {
        $this->resource = $resource;
    }
}
