<?php

namespace App\Actions\InvitationImports;

use App\Contracts\InvitationImports\ConfirmsInvitationImports;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiInvitationImportOutcome;
use App\Enums\KnowiiInvitationImportRefusal;
use App\Enums\KnowiiInvitationImportState;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\InvitationImport;
use App\Models\InvitationImportRow;
use App\Models\User;
use App\Support\InvitationImports\InvitationImportCheckedRow;
use App\Support\InvitationImports\InvitationImportCheckInput;
use App\Support\InvitationImports\InvitationImportChecks;
use App\Support\InvitationImports\InvitationImportMailChecks;
use App\Support\InvitationImports\InvitationImports;
use App\Support\InvitationMailCaps;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Visus\Cuid2\Cuid2;

class ConfirmInvitationImport implements ConfirmsInvitationImports
{
    /**
     * The invitations go in with one statement per this many rows
     */
    private const int INSERT_CHUNK = 500;

    /**
     * Send a preview (#1390, ADR 0061): only the person who uploaded it, who saw it. In one transaction, with the import
     * row then the community row locked, `for no key update` (JoinCommunity locks it `for update`: the two wait for
     * each other), then the locks of the daily caps (InvitationMailCaps::lock(): the community, then the account), so
     * two confirms never both pass:
     *
     * 1. every valid row is checked again for the confirmer (InvitationImportChecks): a row that fails now is refused with
     *    the reason that applies now;
     * 2. the checks of the mail for the rows that stay valid (InvitationImportMailChecks: the hold, the month, the daily
     *    caps of the community and of the confirmer): each refuses the whole import, and nothing changes;
     * 3. for each valid row, the expired invitation of the address goes, and the invitation is inserted with
     *    `on conflict do nothing`: a single invitation of the same address that came meanwhile refuses the row
     *    (`pending_invitation`), with no error. The links of its groups and tags follow, and the row is `queued`.
     *
     * Each queued row counts in the daily caps (InvitationMailCaps) and gets `queued_count` 1. After the commit: one job
     * per queued row, one CommunityMembersChanged, one InvitationImportChanged, one log line.
     *
     * @throws AuthorizationException
     * @throws BusinessException
     */
    final public function confirm(User $user, InvitationImport $import): InvitationImport
    {
        $community = $import->community;
        Gate::forUser($user)->authorize('addCommunityMember', $community);
        InvitationImports::assertAvailable($import);
        if ($import->user_id !== $user->id) {
            throw new AuthorizationException('Only the person who uploaded the file sends it');
        }

        /** @var array{queued: array<int, int>, refused: int} $result */
        $result = DB::transaction(static function () use ($user, $community, $import): array {
            $locked = InvitationImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($locked->state !== KnowiiInvitationImportState::Preview) {
                throw new BusinessException('This import is sent already');
            }
            // `for no key update` (JoinCommunity locks it `for update`: the two wait for each other); an insert that
            // refers to the community does not wait
            Community::query()->whereKey($community->id)->lock('for no key update')->firstOrFail(['id']);
            InvitationMailCaps::lock($user, $community);

            /** @var list<InvitationImportRow> $rows */
            $rows = $locked->rows()->where('outcome', KnowiiInvitationImportOutcome::Valid->value)->orderBy('row_number')->get()->all();
            $inputs = array_map(static fn (InvitationImportRow $row) => InvitationImportCheckInput::fromRow($row), $rows);
            $checked = InvitationImportChecks::check($user, $community, $inputs, KnowiiCommunityMemberRole::Member);

            $valid = [];
            $refusedNow = 0;
            foreach ($rows as $index => $row) {
                $check = $checked[$index];
                if ($check->reason !== null) {
                    self::refuse($row, $check->reason, $check->detail);
                    $refusedNow++;
                } else {
                    $valid[] = [$row, $check];
                }
            }

            InvitationImportMailChecks::assertCanSend($user, $community, count($valid));

            $queued = [];
            $now = CarbonImmutable::now();
            foreach (array_chunk($valid, self::INSERT_CHUNK) as $chunk) {
                $ids = self::insertInvitations($community, $chunk, $now);
                foreach ($chunk as [$row, $check]) {
                    $invitationId = $ids[$row->email] ?? null;
                    if ($invitationId === null) {
                        // An invitation of this address came between the preview and now
                        self::refuse($row, KnowiiInvitationImportRefusal::PendingInvitation, self::pendingEnd($community, $row->email));
                        $refusedNow++;

                        continue;
                    }
                    self::link($invitationId, $check);
                    $row->forceFill([
                        'outcome' => KnowiiInvitationImportOutcome::Queued,
                        'community_invitation_id' => $invitationId,
                        'queued_at' => $now,
                        'queued_count' => $row->queued_count + 1,
                    ])->save();
                    $queued[$row->id] = $row->queued_count;
                }
            }

            InvitationMailCaps::record($user, $community, count($queued));
            $locked->forceFill(['state' => KnowiiInvitationImportState::Sending, 'confirmed_at' => $now])->save();

            return ['queued' => $queued, 'refused' => $refusedNow];
        });

        InvitationImports::queueMails($result['queued']);
        CommunityMembersChanged::dispatch($community->cuid);
        $import->refresh();
        InvitationImports::notify($import, $community->cuid);
        Log::info('Invitation import sent', [
            'community' => $community->cuid,
            'import' => $import->cuid,
            'queued' => count($result['queued']),
            'refused_now' => $result['refused'],
        ]);
        // Nothing to send: the import ends at once
        if ($result['queued'] === []) {
            SendInvitationImportMails::finishIfDone($import, $community);
            $import->refresh();
        }

        return $import;
    }

    private static function refuse(InvitationImportRow $row, KnowiiInvitationImportRefusal $reason, ?string $detail): void
    {
        $row->forceFill(['outcome' => KnowiiInvitationImportOutcome::Refused, 'reason' => $reason, 'detail' => $detail === null ? null : mb_substr($detail, 0, 64)])->save();
    }

    /**
     * Delete the expired invitations of the addresses, then insert one invitation per row; an address that has an
     * invitation already gets no insert. The ids of the new invitations, by address
     *
     * @param  list<array{0: InvitationImportRow, 1: InvitationImportCheckedRow}>  $chunk
     * @return array<string, int>
     */
    private static function insertInvitations(Community $community, array $chunk, CarbonImmutable $now): array
    {
        $emails = array_map(static fn (array $pair): string => $pair[0]->email, $chunk);
        CommunityInvitation::query()
            ->where('community_id', $community->id)
            ->whereIn(DB::raw('lower(email)'), $emails)
            ->where('created_at', '<=', $now->subDays(CommunityInvitation::VALIDITY_DAYS))
            ->delete();

        $values = [];
        $bindings = [];
        foreach ($chunk as [$row]) {
            $values[] = '(?, ?, ?, ?, ?, ?)';
            array_push($bindings, (new Cuid2)->toString(), $community->id, $row->email, $row->role->value ?? KnowiiCommunityMemberRole::Member->value, $now, $now);
        }
        // Every value is a binding
        $inserted = DB::select(
            'insert into community_invitations (cuid, community_id, email, role, created_at, updated_at) values '.implode(', ', $values)
            .' on conflict (community_id, email) do nothing returning id, email',
            $bindings,
        );

        $ids = [];
        foreach ($inserted as $invitation) {
            if (is_object($invitation) && is_string($invitation->email ?? null) && is_numeric($invitation->id ?? null)) {
                $ids[$invitation->email] = (int) $invitation->id;
            }
        }

        return $ids;
    }

    /**
     * The links of the groups and the tags that the checks resolved again just now
     */
    private static function link(int $invitationId, InvitationImportCheckedRow $check): void
    {
        $now = now();
        $groups = array_map(static fn ($group): array => ['community_invitation_id' => $invitationId, 'access_group_id' => $group->id, 'created_at' => $now], $check->groups);
        $tags = array_map(static fn ($tag): array => ['community_invitation_id' => $invitationId, 'member_tag_id' => $tag->id, 'created_at' => $now], $check->tags);
        if ($groups !== []) {
            DB::table('community_invitation_access_groups')->insert($groups);
        }
        if ($tags !== []) {
            DB::table('community_invitation_member_tags')->insert($tags);
        }
    }

    /**
     * The end of the pending invitation of the address (ISO 8601), for the text of the reason
     */
    private static function pendingEnd(Community $community, string $email): ?string
    {
        $createdAt = CommunityInvitation::query()->where('community_id', $community->id)->whereRaw('lower(email) = ?', [$email])->value('created_at');

        return is_string($createdAt) || $createdAt instanceof \DateTimeInterface
            ? CarbonImmutable::parse($createdAt)->addDays(CommunityInvitation::VALIDITY_DAYS)->toIso8601ZuluString()
            : null;
    }
}
