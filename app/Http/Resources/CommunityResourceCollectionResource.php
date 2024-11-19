<?php

namespace App\Http\Resources;

use App\Models\CommunityResourceCollection;
use Illuminate\Http\Request;

/** @mixin CommunityResourceCollection **/
class CommunityResourceCollectionResource extends AbstractKnowiiJsonResource
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
