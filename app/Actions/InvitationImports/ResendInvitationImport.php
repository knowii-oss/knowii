<?php

namespace App\Actions\InvitationImports;

use App\Contracts\InvitationImports\ResendsInvitationImports;
use App\Enums\KnowiiInvitationImportOutcome;
use App\Enums\KnowiiInvitationImportState;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\InvitationImport;
use App\Models\InvitationImportRow;
use App\Models\User;
use App\Support\CommunityRoles;
use App\Support\InvitationImports\InvitationImportMailChecks;
use App\Support\InvitationImports\InvitationImports;
use App\Support\InvitationMailCaps;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResendInvitationImport implements ResendsInvitationImports
{
    public const string NOT_SENT = 'not_sent';

    public const string NOT_JOINED = 'not_joined';

    /**
     * Send the mails of a set of rows again (#1390, ADR 0061), only rows whose invitation still exists:
     *
     * - `not_sent`: the rows `held` and `failed`, and the rows `queued` before InvitationImports::staleQueuedBefore()
     *   (the time of the retries of their mail and of its last attempt is over), at any time;
     * - `not_joined`: the rows `sent`, one time per import, and not before Constants::INVITATION_IMPORT_RESEND_AFTER_DAYS
     *   days after the confirm.
     *
     * Who: a person who passes `addCommunityMember` and may give the role of every row of the set. The checks of the
     * mail apply to the set (InvitationImportMailChecks, with the daily caps of the community, of this person and of the
     * instance). Then each row is `queued` again with a new `queued_at` and the next `queued_count`, counts in the daily
     * caps, and gets one job: a job of an earlier time of the row that still runs changes nothing.
     *
     * @throws AuthorizationException
     * @throws BusinessException
     * @throws ValidationException
     */
    final public function resend(User $user, InvitationImport $import, array $input): InvitationImport
    {
        $community = $import->community;
        Gate::forUser($user)->authorize('addCommunityMember', $community);
        InvitationImports::assertAvailable($import);

        /** @var array{rows: string} $validated */
        $validated = Validator::make($input, [
            'rows' => ['required', 'string', Rule::in([self::NOT_SENT, self::NOT_JOINED])],
        ])->validate();
        $notJoined = $validated['rows'] === self::NOT_JOINED;

        /** @var array<int, int> $queued */
        $queued = DB::transaction(static function () use ($user, $community, $import, $notJoined): array {
            // The import row, the community row, then the locks of the daily caps (the community, then the account; the
            // instance in the checks of the mail), as the confirm: the counts of the checks below hold until the commit
            $locked = InvitationImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($locked->state === KnowiiInvitationImportState::Preview || $locked->confirmed_at === null) {
                throw new BusinessException('This import is not sent yet');
            }
            // `for no key update` (JoinCommunity locks it `for update`: the two wait for each other); an insert that
            // refers to the community does not wait
            Community::query()->whereKey($community->id)->lock('for no key update')->firstOrFail(['id']);
            InvitationMailCaps::lock($user, $community);
            if ($notJoined) {
                if ($locked->resent_at !== null) {
                    throw new BusinessException('You invited these people again on '.$locked->resent_at->utc()->format('Y-m-d').' already.');
                }
                $from = InvitationImports::resendNotJoinedFrom($locked);
                if ($from !== null && $from->greaterThan(CarbonImmutable::now())) {
                    throw new BusinessException('You can invite these people again from '.$from->utc()->format('Y-m-d H:i').' UTC.');
                }
            }

            $query = $locked->rows()->whereNotNull('community_invitation_id');
            if ($notJoined) {
                $query->where('outcome', KnowiiInvitationImportOutcome::Sent->value);
            } else {
                InvitationImports::whereNotSent($query->getQuery());
            }
            /** @var list<InvitationImportRow> $rows */
            $rows = $query->lockForUpdate()->get()->all();
            if ($rows === []) {
                throw new BusinessException('No row to send');
            }
            foreach ($rows as $row) {
                if ($row->role === null || ! CommunityRoles::canGrant($user, $community, $row->role)) {
                    throw new AuthorizationException('You cannot give the role of every row');
                }
            }

            InvitationImportMailChecks::assertCanSend($user, $community, count($rows));

            $now = CarbonImmutable::now();
            // The rows are locked: the next `queued_count` of each is its value plus one
            $ids = [];
            foreach ($rows as $row) {
                $ids[$row->id] = $row->queued_count + 1;
            }
            InvitationImportRow::query()->whereIn('id', array_keys($ids))->update([
                'outcome' => KnowiiInvitationImportOutcome::Queued->value,
                'queued_at' => $now,
                'queued_count' => DB::raw('queued_count + 1'),
                'updated_at' => $now,
            ]);
            InvitationMailCaps::record($user, $community, count($ids));
            $locked->forceFill([
                'state' => KnowiiInvitationImportState::Sending,
                'finished_at' => null,
                ...($notJoined ? ['resent_at' => $now] : []),
            ])->save();

            return $ids;
        });

        InvitationImports::queueMails($queued);
        $import->refresh();
        InvitationImports::notify($import, $community->cuid);
        Log::info('Invitation import sent again', ['community' => $community->cuid, 'import' => $import->cuid, 'rows' => $validated['rows'], 'queued' => count($queued)]);

        return $import;
    }
}
