<?php

namespace App\Http\Resources;

use App\Models\Community;
use App\Models\User;
use Illuminate\Http\Request;

/** @mixin Community **/
class CommunityResource extends AbstractKnowiiJsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var Community $community */
        $community = $this->resource;
        $user = $request->user();

        return [
            'cuid' => $this->cuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?? '', // Ensures the returned value is never null
            'visibility' => $this->visibility,
            // Every right with its value, also the defaults (#1240, ADR 0017)
            'moderator_permissions' => $this->moderatorPermissions(),
            // Only when the users are counted (loadCount('users')): the owner is not in the pivot, so + 1 (#1135)
            'member_count' => $this->whenCounted('users', fn (int $count) => $count + 1),
            // Whether the current user belongs to the community (#1187: presence is for members only); absent without a user
            'is_member' => $this->when($user instanceof User, fn () => $user instanceof User && $user->belongsToCommunity($community)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
