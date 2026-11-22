<?php

namespace Database\Factories;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiInvitationImportOutcome;
use App\Enums\KnowiiInvitationImportRefusal;
use App\Models\InvitationImport;
use App\Models\InvitationImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<InvitationImportRow>
 */
class InvitationImportRowFactory extends Factory
{
    /**
     * A valid row of a member. The tests name the import, the row number and the address.
     *
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        return [
            'cuid' => (new Cuid2)->toString(),
            'invitation_import_id' => InvitationImport::factory(),
            'row_number' => 2,
            'email' => 'row@example.com',
            'role' => KnowiiCommunityMemberRole::Member,
            'access_group_names' => [],
            'member_tag_names' => [],
            'outcome' => KnowiiInvitationImportOutcome::Valid,
            'reason' => null,
            'detail' => null,
        ];
    }

    final public function refused(KnowiiInvitationImportRefusal $reason, ?string $detail = null): static
    {
        return $this->state(fn () => ['outcome' => KnowiiInvitationImportOutcome::Refused, 'reason' => $reason, 'detail' => $detail]);
    }

    final public function outcome(KnowiiInvitationImportOutcome $outcome): static
    {
        return $this->state(fn () => ['outcome' => $outcome]);
    }
}
