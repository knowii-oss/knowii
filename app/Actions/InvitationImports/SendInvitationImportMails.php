<?php

namespace App\Actions\InvitationImports;

use App\Contracts\InvitationImports\SendsInvitationImportMails;
use App\Enums\KnowiiInvitationImportOutcome;
use App\Enums\KnowiiInvitationImportState;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Mail\CommunityInvitation as CommunityInvitationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\InvitationImport;
use App\Models\InvitationImportRow;
use App\Support\InvitationImports\InvitationImportChecks;
use App\Support\InvitationImports\InvitationImports;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInvitationImportMails implements SendsInvitationImportMails
{
    /**
     * The live update goes out each time this many rows of an import ended
     */
    public const int NOTIFY_EVERY = 25;

    /**
     * The mail of one row (#1390, ADR 0061), from the job SendInvitationImportMail, so the limiter and the hold of the job
     * apply to the send (ADR 0054 consequence 6):
     *
     * - the invitation is gone (cancelled, accepted), also between its read and its update: the row is `skipped`;
     * - the address is a member now: the row is `skipped`, and the invitation goes;
     * - else the invitation counts from now (its link is valid 7 days from the mail), the mail of a single invitation goes
     *   out in the name of the person who uploaded the file (else the owner of the community), and the row is `sent`.
     *
     * A row that is not queued (its record was deleted, or it ended already), or that went to the queue again since this
     * job (another `queued_count`), changes nothing.
     */
    final public function send(int $rowId, int $queuedCount): void
    {
        $row = InvitationImportRow::query()->with(['invitationImport.community.owner', 'invitationImport.user'])->find($rowId);
        if ($row === null || ! self::isQueued($row, $queuedCount)) {
            return;
        }
        $import = $row->invitationImport;
        $community = $import->community;

        $invitation = $row->community_invitation_id === null ? null : CommunityInvitation::query()->find($row->community_invitation_id);
        if ($invitation === null) {
            $this->end($row, $queuedCount, KnowiiInvitationImportOutcome::Skipped);

            return;
        }
        if (InvitationImportChecks::memberEmails($community, [mb_strtolower($invitation->email)]) !== []) {
            $invitation->delete();
            CommunityMembersChanged::dispatch($community->cuid);
            $this->end($row, $queuedCount, KnowiiInvitationImportOutcome::Skipped);

            return;
        }

        // The invitation counts from now. An accept or a cancel that ended after the read above leaves no row to
        // change: then no mail goes, and the row is `skipped`
        $now = now();
        if (CommunityInvitation::query()->whereKey($invitation->id)->update(['created_at' => $now]) !== 1) {
            $this->end($row, $queuedCount, KnowiiInvitationImportOutcome::Skipped);

            return;
        }
        $invitation->forceFill(['created_at' => $now])->syncOriginal();
        $invitation->setRelation('community', $community);
        Mail::to($invitation->email)->send(new CommunityInvitationMail($invitation, $import->user ?? $community->owner));

        $this->end($row, $queuedCount, KnowiiInvitationImportOutcome::Sent, ['sent_at' => now()]);
    }

    final public function hold(int $rowId, int $queuedCount): void
    {
        $row = InvitationImportRow::query()->with('invitationImport.community')->find($rowId);
        if ($row !== null && self::isQueued($row, $queuedCount)) {
            $this->end($row, $queuedCount, KnowiiInvitationImportOutcome::Held);
        }
    }

    final public function fail(int $rowId, int $queuedCount): void
    {
        $row = InvitationImportRow::query()->with('invitationImport.community')->find($rowId);
        if ($row !== null && self::isQueued($row, $queuedCount)) {
            $this->end($row, $queuedCount, KnowiiInvitationImportOutcome::Failed);
        }
    }

    /**
     * The import is `done` when no row of it waits: one conditional statement, so two workers never both write it. Then
     * one CommunityMembersChanged and the live update
     */
    public static function finishIfDone(InvitationImport $import, Community $community): bool
    {
        $done = InvitationImport::query()
            ->whereKey($import->id)
            ->where('state', KnowiiInvitationImportState::Sending->value)
            ->whereNotExists(static fn (QueryBuilder $query) => $query
                ->select(DB::raw(1))
                ->from('invitation_import_rows')
                ->whereColumn('invitation_import_rows.invitation_import_id', 'invitation_imports.id')
                ->where('invitation_import_rows.outcome', KnowiiInvitationImportOutcome::Queued->value))
            ->update(['state' => KnowiiInvitationImportState::Done->value, 'finished_at' => now(), 'updated_at' => now()]) === 1;

        if ($done) {
            Log::info('Invitation import done', ['community' => $community->cuid, 'import' => $import->cuid]);
            CommunityMembersChanged::dispatch($community->cuid);
            InvitationImports::notify($import, $community->cuid);
        }

        return $done;
    }

    /**
     * The row waits for the mail of this job: `queued`, with the `queued_count` of the job
     */
    private static function isQueued(InvitationImportRow $row, int $queuedCount): bool
    {
        return $row->outcome === KnowiiInvitationImportOutcome::Queued && $row->queued_count === $queuedCount;
    }

    /**
     * @param  array<string, CarbonInterface>  $more
     */
    private function end(InvitationImportRow $row, int $queuedCount, KnowiiInvitationImportOutcome $outcome, array $more = []): void
    {
        $import = $row->invitationImport;
        $community = $import->community;
        // Only a row queued for this job ends: a second worker on the same row, or a job of an earlier time of the row,
        // changes nothing
        $changed = InvitationImportRow::query()
            ->whereKey($row->id)
            ->where('outcome', KnowiiInvitationImportOutcome::Queued->value)
            ->where('queued_count', $queuedCount)
            ->update(['outcome' => $outcome->value, 'updated_at' => now(), ...$more]);
        if ($changed === 0) {
            return;
        }
        Log::info('Invitation import row ended', ['community' => $community->cuid, 'import' => $import->cuid, 'row' => $row->cuid, 'outcome' => $outcome->value]);

        if (! self::finishIfDone($import, $community)) {
            $ended = InvitationImportRow::query()
                ->where('invitation_import_id', $import->id)
                ->whereIn('outcome', array_map(static fn (KnowiiInvitationImportOutcome $item): string => $item->value, KnowiiInvitationImportOutcome::ended()))
                ->count();
            if ($ended % self::NOTIFY_EVERY === 0) {
                InvitationImports::notify($import, $community->cuid);
            }
        }
    }
}
