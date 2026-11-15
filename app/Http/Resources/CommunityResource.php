<?php

namespace App\Http\Resources;

use App\Models\Community;
use App\Models\User;
use App\Support\Conversations;
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
        $isMember = $user instanceof User && $user->belongsToCommunity($community);

        // Someone who is not a member (a visitor, a signed-in outsider) reads the public spaces of the community and
        // gets what the reader needs, nothing else: no moderator rights, no member count, no direct message setting
        // (#1387, ADR 0044)
        if (! $isMember) {
            return [
                'cuid' => $this->cuid,
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description ?? '', // Ensures the returned value is never null
                'visibility' => $this->visibility,
                'is_member' => false,
            ];
        }

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
            // Whether the current user belongs to the community (#1187: presence is for members only)
            'is_member' => true,
            // Direct messages (#1186): whether members can write them, and (pages of the community only) what the member did not read
            'direct_messages_enabled' => $community->allowsDirectMessages(),
            'unread_direct_messages' => $this->when(
                $this->serializeLargeFields,
                fn () => Conversations::unreadTotal($user, $community),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
