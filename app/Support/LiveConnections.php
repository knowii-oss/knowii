<?php

namespace App\Support;

use App\Jobs\EndLiveConnections;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;

/**
 * When the access of an account ends or shrinks, its live connections end (ADR 0075): the authorization of every
 * channel is signed with the account (App\Broadcasting\AccountBoundBroadcaster), so the WebSocket server finds each of
 * its connections. The pages connect again by themselves, and each channel asks its rule again. The call is a job of the
 * queue `broadcasts`, sent after the commit of the change: a change that is undone ends nothing.
 */
final class LiveConnections
{
    /**
     * The accounts of one job: each one is a call to the WebSocket server
     */
    public const int ACCOUNTS_PER_JOB = 100;

    /**
     * End the live connections of these accounts
     */
    public static function endFor(User|string ...$accounts): void
    {
        $cuids = array_values(array_unique(array_map(static fn (User|string $account): string => $account instanceof User ? $account->cuid : $account, $accounts)));

        foreach (array_chunk($cuids, self::ACCOUNTS_PER_JOB) as $chunk) {
            EndLiveConnections::dispatch($chunk);
        }
    }

    /**
     * End the live connections of every member of the community and of its owner: for a change of the access of many
     * members at once, when the list of the ones who lost something costs more than a new connection of the others
     */
    public static function endForMembersOf(Community $community): void
    {
        /** @var list<string> $cuids */
        $cuids = User::query()
            ->whereKey($community->owner_id)
            ->orWhereIn('id', CommunityMember::query()->where('community_id', $community->id)->select('user_id'))
            ->pluck('cuid')
            ->all();

        self::endFor(...$cuids);
    }

    /**
     * End the live connections of these accounts, given by their ids
     *
     * @param  list<int>  $userIds
     */
    public static function endForIds(array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        /** @var list<string> $cuids */
        $cuids = User::query()->whereIn('id', $userIds)->pluck('cuid')->all();
        self::endFor(...$cuids);
    }
}
