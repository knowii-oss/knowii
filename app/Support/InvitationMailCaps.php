<?php

namespace App\Support;

use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The daily caps of the invitation mails (#1390, ADR 0061). Every invitation mail counts: a single invitation, a row of an
 * import at its confirm, a row of a send again. A community sends at most MailSettings::invitationsPerCommunityPerDay()
 * of them in 24 hours, and an account at most MailSettings::invitationsPerAccountPerDay(), over all its communities (the
 * account of the manager who sends).
 *
 * The count is the rows of `invitation_mails` of the last 24 hours: one per mail that went to the queue, written in the
 * transaction that queues it. A delete of an invitation, of an import or of its record leaves them, so nothing that a
 * manager deletes lowers the count. The caller holds a transaction and calls lock() before it reads what the count
 * decides, so two requests never both pass on the same count.
 */
final class InvitationMailCaps
{
    public const int WINDOW_HOURS = 24;

    /**
     * The namespaces of the advisory locks of the caps (the first key of `pg_advisory_xact_lock`; the second is the id)
     */
    public const int LOCK_COMMUNITY = 1390_01;

    public const int LOCK_ACCOUNT = 1390_02;

    /**
     * Lock the caps of the community, then of the account (always in this order), until the end of the transaction of
     * the caller. Advisory locks of the transaction: they serialize the requests that count the same caps, and no
     * foreign key check, no update and no row lock of the `communities` and `users` rows waits for them. A second call in
     * the same transaction does not wait: it holds them already.
     */
    public static function lock(User $user, Community $community): void
    {
        DB::select('select pg_advisory_xact_lock(?, ?)', [self::LOCK_COMMUNITY, self::key($community->id)]);
        DB::select('select pg_advisory_xact_lock(?, ?)', [self::LOCK_ACCOUNT, self::key($user->id)]);
    }

    /**
     * Refuse when $count more mails would pass a cap, with a BusinessException (422) that says when they can go. Both
     * caps are computed: the words are those of the cap that frees last, with the fewest that can go now and the later
     * time. A single invitation gets the time from when it works again; a set gets how many can go now, and from when
     * all of them can.
     *
     * @throws BusinessException
     */
    public static function assertRoom(User $user, Community $community, int $count, bool $single = false): void
    {
        self::lock($user, $community);

        $since = CarbonImmutable::now()->subHours(self::WINDOW_HOURS);
        $caps = [
            ['community_id', $community->id, MailSettings::invitationsPerCommunityPerDay(), 'A community sends at most :cap invitation emails in 24 hours.'],
            ['user_id', $user->id, MailSettings::invitationsPerAccountPerDay(), 'An account sends at most :cap invitation emails in 24 hours, in all its communities.'],
        ];
        $canGo = PHP_INT_MAX;
        $refusal = null;
        foreach ($caps as [$column, $id, $cap, $words]) {
            $used = DB::table('invitation_mails')->where($column, $id)->where('created_at', '>', $since)->count();
            $left = max(0, $cap - $used);
            $canGo = min($canGo, $left);
            if ($used + $count <= $cap) {
                continue;
            }

            // Null: never in one go, the set is larger than the cap
            $from = $count <= $cap ? self::freeFrom($column, $id, $since, $used + $count - $cap) : null;
            if ($refusal === null || self::later($from, $refusal['from'])) {
                $refusal = ['message' => str_replace(':cap', (string) $cap, $words), 'from' => $from];
            }
        }
        if ($refusal === null) {
            return;
        }

        ['message' => $message, 'from' => $from] = $refusal;
        if ($single) {
            $message .= $from === null ? '' : ' Invite this person from '.self::format($from).'.';
        } else {
            $message .= ' '.$canGo.' more can go now.';
            $message .= $from === null ? '' : ($count === 1 ? ' This invitation' : ' These '.$count.' invitations').' can go from '.self::format($from).'.';
        }

        throw new BusinessException($message);
    }

    /**
     * Count $count mails that go to the queue now. In the transaction of assertRoom()
     */
    public static function record(User $user, Community $community, int $count): void
    {
        if ($count < 1) {
            return;
        }
        $row = ['community_id' => $community->id, 'user_id' => $user->id, 'created_at' => CarbonImmutable::now()];
        foreach (array_chunk(array_fill(0, $count, $row), 500) as $chunk) {
            DB::table('invitation_mails')->insert($chunk);
        }
    }

    /**
     * When $need rows of the window have left it: the time of the oldest of them, plus the window
     */
    private static function freeFrom(string $column, int $id, CarbonImmutable $since, int $need): ?CarbonImmutable
    {
        $createdAt = DB::table('invitation_mails')
            ->where($column, $id)
            ->where('created_at', '>', $since)
            ->orderBy('created_at')
            ->orderBy('id')
            ->offset($need - 1)
            ->value('created_at');

        return is_string($createdAt) ? CarbonImmutable::parse($createdAt)->addHours(self::WINDOW_HOURS) : null;
    }

    /**
     * $a frees later than $b: null (never in one go) is the latest
     */
    private static function later(?CarbonImmutable $a, ?CarbonImmutable $b): bool
    {
        return $b !== null && ($a === null || $a->greaterThan($b));
    }

    /**
     * The second key of an advisory lock is an int4: an id past it shares a key with a smaller one, which only waits more
     */
    private static function key(int $id): int
    {
        return $id % 2147483647;
    }

    private static function format(CarbonImmutable $time): string
    {
        // A time that falls within a minute shows the next minute: the cap has room from then
        return $time->utc()->ceilMinute()->format('Y-m-d H:i').' UTC';
    }
}
