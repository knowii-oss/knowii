<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property bool $serializeLargeFields
 */
class AbstractKnowiiJsonResource extends JsonResource
{
    protected bool $serializeLargeFields;

    public function __construct($resource, bool $serializeLargeFields = false)
    {
        parent::__construct($resource);
        $this->serializeLargeFields = $serializeLargeFields;
    }
}
