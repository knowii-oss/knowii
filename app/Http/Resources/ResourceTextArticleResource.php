<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ResourceTextArticleResource extends AbstractKnowiiJsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'cuid' => $this->cuid,

            // the 'html' field is not exposed

            'markdown' => $this->serializeLargeFields ? $this->markdown : null,

            'word_count' => $this->word_count,
            'reading_time' => $this->reading_time,

            'resource' => $this->whenLoaded('resource') ? new ResourceResource($this->whenLoaded('resource'), $this->serializeLargeFields) : null,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
