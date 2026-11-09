<?php

namespace App\Http\Resources;

use App\Models\Upload;
use Illuminate\Http\Request;

/**
 * @mixin Upload
 */
class UploadResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with uploadSchema (upload.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'cuid' => $this->cuid,
            'url' => $this->url(),
            'mime_type' => $this->mime_type,
            'width' => $this->width,
            'height' => $this->height,
            'size_bytes' => $this->size_bytes,
            'alt' => $this->alt,
            'created_at' => $this->created_at,
        ];
    }
}
