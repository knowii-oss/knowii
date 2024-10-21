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

  /**
   * Get the data to broadcast.
   *
   * @return array<string, mixed>
   */
  public function broadcastWith(): array
  {
    return (new ResourceTextArticleResource($this->resourceTextArticle))->toArray(request());
  }

  final public function broadcastOn(): array
  {
    // Emit events to the community channel
    $retVal = [
      new PrivateChannel(Str::of(Constants::$WS_CHANNEL_COMMUNITY)->replace(Constants::$WS_CHANNEL_COMMUNITIES_COMMUNITY_PARAM_COMMUNITY_CUID, $this->community->cuid)),
    ];

    // Emit events about public communities to the public channel
    if (KnowiiCommunityVisibility::Public === $this->community->visibility) {
      $retVal[] = new PrivateChannel(Constants::$WS_CHANNEL_COMMUNITIES);
    }

    return $retVal;
  }

}
