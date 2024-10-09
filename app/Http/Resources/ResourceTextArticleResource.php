<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceTextArticleResource extends JsonResource
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

          'html' => $this->html,
          'markdown' => $this->content, // Ensures description is never null when returning data

          'word_count' => $this->word_count,
          'reading_time' => $this->reading_time,

          'resource' => ResourceResource::make($this->whenLoaded('resource')),

          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
