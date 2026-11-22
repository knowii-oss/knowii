<?php

namespace App\Actions\InvitationImports;

use App\Contracts\InvitationImports\DeletesInvitationImports;
use App\Enums\KnowiiInvitationImportOutcome;
use App\Enums\KnowiiInvitationImportState;
use App\Exceptions\BusinessException;
use App\Models\InvitationImport;
use App\Models\InvitationImportRow;
use App\Models\User;
use App\Support\CommunityRoles;
use App\Support\InvitationImports\InvitationImports;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class DeleteInvitationImport implements DeletesInvitationImports
{
    public const string MAILS_GO_OUT = 'The mails of this import are going out. Delete this record when they are sent.';

    /**
     * The record of a sent import stays at least this long after its last row went to the queue
     */
    public const int KEPT_HOURS_AFTER_LAST_MAIL = 24;

    /**
     * Discard a preview (the person who uploaded it, the owner, the admins), or delete the record of a sent import before
     * its time (the owner and the admins): its invitations stay, and its rows go with it (#1390, ADR 0061). The record of
     * a sent import can go only when its mails went out: not while a row waits for its mail (queued from
     * InvitationImports::staleQueuedBefore()), and not before 24 hours after the last row went to the queue (422 with
     * the date).
     *
     * @throws AuthorizationException
     * @throws BusinessException
     */
    final public function delete(User $user, InvitationImport $import): void
    {
        $community = $import->community;
        Gate::forUser($user)->authorize('addCommunityMember', $community);
        InvitationImports::assertAvailable($import);

        // The import row is locked as a confirm and a send again lock it: its state holds, and no row goes to the queue
        // between the checks and the delete
        $isPreview = DB::transaction(static function () use ($user, $community, $import): bool {
            $locked = InvitationImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            $isPreview = $locked->state === KnowiiInvitationImportState::Preview;
            $allowed = CommunityRoles::isOwnerOrAdmin($user, $community) || ($isPreview && $locked->user_id === $user->id);
            if (! $allowed) {
                throw new AuthorizationException($isPreview ? 'Only the person who uploaded the file, the owner and the admins discard it' : 'Only the owner and the admins delete this record');
            }
            if (! $isPreview) {
                self::assertMailsDone($locked);
            }
            $locked->delete();

            return $isPreview;
        });

        Log::info($isPreview ? 'Invitation import discarded' : 'Invitation import record deleted', ['community' => $community->cuid, 'import' => $import->cuid]);
    }

    /**
     * @throws BusinessException
     */
    private static function assertMailsDone(InvitationImport $import): void
    {
        $rows = InvitationImportRow::query()->where('invitation_import_id', $import->id);
        $waiting = (clone $rows)
            ->where('outcome', KnowiiInvitationImportOutcome::Queued->value)
            ->where('queued_at', '>=', InvitationImports::staleQueuedBefore())
            ->exists();
        if ($waiting) {
            throw new BusinessException(self::MAILS_GO_OUT);
        }

        $lastQueued = (clone $rows)->max('queued_at');
        $from = is_string($lastQueued) ? CarbonImmutable::parse($lastQueued)->addHours(self::KEPT_HOURS_AFTER_LAST_MAIL) : null;
        if ($from !== null && $from->greaterThan(CarbonImmutable::now())) {
            throw new BusinessException('You can delete this record from '.$from->utc()->ceilMinute()->format('Y-m-d H:i').' UTC.');
        }
    }
}
