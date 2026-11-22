<?php

namespace Database\Factories;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiInvitationImportState;
use App\Models\Community;
use App\Models\InvitationImport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<InvitationImport>
 */
class InvitationImportFactory extends Factory
{
    /**
     * A preview with no default in a new community. The tests name the community and the person who uploaded it.
     *
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        return [
            'cuid' => (new Cuid2)->toString(),
            'community_id' => Community::factory(),
            'user_id' => null,
            'state' => KnowiiInvitationImportState::Preview,
            'default_role' => KnowiiCommunityMemberRole::Member,
            'default_access_groups' => [],
            'default_member_tags' => [],
            'ignored_columns' => [],
        ];
    }

    /**
     * An import whose mails go out, confirmed now
     */
    final public function sending(): static
    {
        return $this->state(fn () => ['state' => KnowiiInvitationImportState::Sending, 'confirmed_at' => now()]);
    }

    /**
     * An import whose mails all ended, confirmed now
     */
    final public function done(): static
    {
        return $this->state(fn () => ['state' => KnowiiInvitationImportState::Done, 'confirmed_at' => now(), 'finished_at' => now()]);
    }
}
