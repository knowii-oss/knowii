<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityResource extends JsonResource
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
          'description' => $this->description ?? '', // Ensures the returned value is never null
          'visibility' => $this->visibility,
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
