<?php

namespace App\Events\Resources\TextArticles;

use Illuminate\Queue\SerializesModels;

class ResourceTextArticleUpdated extends ResourceTextArticleEvent
{
  use SerializesModels;
}
