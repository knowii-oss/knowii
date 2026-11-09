<?php

namespace App\Http\Resources;

use App\Constants;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;

/**
 * What a member shows to the other members of a community (#1177): never the email; the other fields as the member
 * chose (#1076, ADR 0020), the phone hidden by default.
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
                // The member chooses what the other members see (#1076, ADR 0020)
                if ($profile->showsField($property)) {
                    $links[$property] = (string) $profile->{$property};
                }
            }
        }

        return [
            'cuid' => $this->cuid,
            'name' => $this->name,
            'username' => $this->username,
            'bio' => $profile?->showsField('bio') ? (string) $profile->bio : '',
            'location' => $profile?->showsField('location') ? (string) $profile->location : '',
            // Hidden unless the member shows it
            'phone' => $profile?->showsField('phone') ? (string) $profile->phone : '',
            'social_links' => (object) $links,
            'member_since' => $this->created_at,
        ];
    }
}
