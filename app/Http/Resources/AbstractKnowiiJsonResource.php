<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base class of the Knowii API resources. Models declare their resource with
 * #[UseResource], so code writes $model->toResource() and
 * $collection->toResourceCollection() (see documentation/architecture/backend.md).
 */
class AbstractKnowiiJsonResource extends JsonResource
{
    /**
     * Whether the large fields (Markdown sources, article bodies, base64 images) are serialized.
     */
    protected bool $serializeLargeFields = false;

    /**
     * Serialize the large fields too (for single-item responses and edit forms, not for lists).
     */
    final public function withLargeFields(bool $serializeLargeFields = true): static
    {
        $this->serializeLargeFields = $serializeLargeFields;

        return $this;
    }
}
