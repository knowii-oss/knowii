<?php

namespace App\Events\Resources;

use Illuminate\Queue\SerializesModels;

class ResourceCreated extends ResourceEvent
{
  use SerializesModels;
}
