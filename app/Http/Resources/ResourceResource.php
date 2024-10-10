<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceResource extends JsonResource
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
          'name' => $this->name,
          'slug' => $this->slug,
          'excerpt' => $this->excerpt ?? '', // Ensures the returned value is never null
          'keywords' => $this->keywords ?? [], // Ensures the returned value is never null
          'description' => $this->description ?? '', // Ensures the returned value is never null
          'ai_summary' => $this->ai_summary ?? '', // Ensures the returned value is never null
          'published_at' => $this->published_at,
          'modified_at' => $this->modified_at,
          'language' => $this->language ?? '', // Ensures the returned value is never null
          'url' => $this->url ?? '', // Ensures the returned value is never null
          'cover_image_url' => $this->cover_image_url,
          'cover_image_alt' => $this->cover_image_alt, // Ensures the returned value is never null
          'cover_image_base64' => $this->cover_image_base64,
          'type' => $this->type->value,
          'level' => $this->level->value,
          'is_featured' => $this->is_featured,
          'view_count' => $this->view_count,
          'share_count' => $this->share_count,
          'last_captured_at' => $this->last_captured_at,
          'last_checked_at' => $this->last_checked_at,
          'check_failures_count' => $this->check_failures_count,
          'is_unavailable' => $this->is_unavailable,

          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
