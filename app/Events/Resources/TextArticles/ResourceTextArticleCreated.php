<?php

namespace App\Events\Resources\TextArticles;

use Illuminate\Queue\SerializesModels;

class ResourceTextArticleCreated extends ResourceTextArticleEvent
{
    use SerializesModels;
}
