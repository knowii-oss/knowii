<?php

namespace App\Http\Resources;

use App\Models\CourseMaterial;
use Illuminate\Http\Request;

/**
 * A material of a course (#1336). The place of a file on the disk is never sent: the client downloads it through
 * the download endpoint, which checks the access again.
 *
 * @mixin CourseMaterial
 */
class CourseMaterialResource extends AbstractKnowiiJsonResource
{
    /**
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'cuid' => $this->cuid,
            'kind' => $this->kind,
            'title' => $this->title,
            'position' => $this->position,
            // The section or the lesson; null: the whole course
            'page_cuid' => $this->page?->cuid,
            // A link
            'url' => $this->url,
            // A file
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
