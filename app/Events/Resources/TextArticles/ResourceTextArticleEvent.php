<?php

namespace App\Events\Resources\TextArticles;

use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Http\Resources\ResourceTextArticleResource;
use App\Models\ResourceTextArticle;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

abstract class ResourceTextArticleEvent implements ShouldDispatchAfterCommit
{
  use Dispatchable;

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
