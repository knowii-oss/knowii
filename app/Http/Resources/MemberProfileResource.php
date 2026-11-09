<?php

namespace App\Http\Resources;

use App\Constants;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;

/**
 * What a member shows to the other members of a community: no email, no phone (#1177).
 *
 * @mixin User
 */
class MemberProfileResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with memberProfileSchema (member.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var UserProfile|null $profile Users created outside the registration may have no profile */
        $profile = $this->profile;
        $links = [];
        if ($profile !== null) {
            foreach (Constants::SOCIAL_MEDIA_LINK_PROPERTIES as $property) {
                $value = $profile->{$property};
                if (is_string($value) && $value !== '') {
                    $links[$property] = $value;
                }
            }
        }

        return [
            'cuid' => $this->cuid,
            'name' => $this->name,
            'username' => $this->username,
            'bio' => $profile->bio ?? '',
            'location' => $profile->location ?? '',
            'social_links' => (object) $links,
            'member_since' => $this->created_at,
        ];
    }
}
