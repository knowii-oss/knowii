<?php

namespace App\Actions\Users;

use App\Constants;
use App\Contracts\Users\RecordsLastVisits;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The last visit of a member is one date without an hour (#1476, ADR 0048, design 8.5): replaced at most once a day, read
 * by nobody. The write compares first, and does not touch `updated_at`.
 */
class RecordLastVisit implements RecordsLastVisits
{
    final public function record(User $user): ?string
    {
        $attributes = $user->getAttributes();
        // A model built in memory (not read from the database) does not hold the column yet: read it
        $stored = array_key_exists('last_visit_on', $attributes) ? $attributes['last_visit_on'] : User::query()->whereKey($user->id)->toBase()->value('last_visit_on');

        $today = CarbonImmutable::now('UTC')->startOfDay();
        $previous = is_string($stored) && $stored !== ''
            ? CarbonImmutable::parse(substr($stored, 0, 10), 'UTC')->startOfDay()
            : null;

        if ($previous !== null && $previous->equalTo($today)) {
            return null;
        }

        User::query()->whereKey($user->id)->toBase()->update(['last_visit_on' => $today->toDateString()]);
        $user->setRawAttributes([...$attributes, 'last_visit_on' => $today->toDateString()], true);

        // A new account (no date) has nothing to catch up on
        if ($previous === null || (int) floor($previous->diffInDays($today, true)) < Constants::CATCH_UP_AWAY_DAYS) {
            return null;
        }

        return $previous->toDateString();
    }
}
