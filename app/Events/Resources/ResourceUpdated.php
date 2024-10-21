<?php

namespace App\Events\Resources;

use Illuminate\Queue\SerializesModels;

class ResourceUpdated extends ResourceEvent
{
  use SerializesModels;
}
