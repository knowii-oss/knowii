<?php

namespace App\Events\Resources\TextArticles;

use App\Models\ResourceTextArticle;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

abstract class ResourceTextArticleEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * The resource instance.
     */
    public ResourceTextArticle $resourceTextArticle;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(ResourceTextArticle $resourceTextArticle)
    {
        $this->resourceTextArticle = $resourceTextArticle;
    }
}
