<?php

namespace App\Events\Resources\TextArticles;

use App\Models\ResourceTextArticle;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class ResourceTextArticleEvent
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  /**
   * The resource instance.
   *
   * @var ResourceTextArticle
   */
  public ResourceTextArticle $resourceTextArticle;

  /**
   * Create a new event instance.
   *
   * @param ResourceTextArticle $resourceTextArticle
   * @return void
   */
  public function __construct(ResourceTextArticle $resourceTextArticle)
  {
    $this->resourceTextArticle = $resourceTextArticle;
  }
}
