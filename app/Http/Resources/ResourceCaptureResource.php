<?php

namespace App\Http\Resources;

use App\Models\ResourceCapture;
use Illuminate\Http\Request;

/**
 * The captured content of a community resource (ADR 0018). The Markdown is a large field: only the single resource
 * carries it, with withLargeFields().
 *
 * @mixin ResourceCapture
 */
class ResourceCaptureResource extends AbstractKnowiiJsonResource
{
    /**
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'markdown' => $this->serializeLargeFields ? $this->markdown : null,
            'word_count' => $this->word_count,
            'reading_time' => $this->reading_time,
            'source' => $this->source->value,
            'captured_at' => $this->captured_at,
        ];
    }
}
