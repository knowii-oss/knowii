<?php

namespace App\Http\Resources;

use App\Models\Resource;
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
          'name' => $this->name,
          'slug' => $this->slug,
          'description' => $this->description ?? '', // Ensures description is never null when returning data

          // FIXME add right fields

          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
