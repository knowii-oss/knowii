<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AbstractKnowiiJsonResource  extends JsonResource
{
  protected $serializeLargeFields;

  public function __construct($resource, bool $serializeLargeFields = false)
  {
    parent::__construct($resource);
    $this->serializeLargeFields = $serializeLargeFields;
  }
}
