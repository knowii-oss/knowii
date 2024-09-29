<?php

namespace App\Events\Resources;

use App\Models\Resource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class ResourceEvent
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  /**
   * The resource instance.
   *
   * @var Resource
   */
  public Resource $resource;

  /**
   * Create a new event instance.
   *
   * @param Resource $resource
   * @return void
   */
  public function __construct(Resource $resource)
  {
    $this->resource = $resource;
  }
}
