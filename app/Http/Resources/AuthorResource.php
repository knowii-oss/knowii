<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;

/**
 * The public summary of a member shown next to what they wrote.
 *
 * @mixin User
 */
class AuthorResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with authorSchema (author.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'cuid' => $this->cuid,
            'name' => $this->name,
            'username' => $this->username,
            // The curator of a resource is a UserProfile, which has no avatar of its own (ADR 0021)
            'avatar_url' => $this->resource instanceof User ? $this->resource->avatar_url : null,
        ];
    }
}
