<?php

namespace App\Support\InvitationImports;

use App\Enums\KnowiiInvitationImportOutcome;
use App\Models\InvitationImport;
use Illuminate\Support\Facades\DB;

/**
 * The counts of the rows of imports (#1390, ADR 0061), from one grouped query for a whole page: never stored as counters.
 * `still_invited` counts the rows whose invitation still exists (not accepted, not cancelled), and `not_sent` the rows
 * that "Send the mails that were not sent" takes: held, failed, or queued for longer than the retries of a mail, with an
 * invitation that still exists.
 */
final class InvitationImportCounts
{
    public const array KEYS = ['rows', 'valid', 'refused', 'queued', 'sent', 'held', 'failed', 'skipped', 'still_invited', 'not_sent'];

    /**
     * Put the counts on each import (InvitationImport::$rowCounts)
     *
     * @param  iterable<InvitationImport>  $imports
     */
    public static function attach(iterable $imports): void
    {
        $byId = [];
        foreach ($imports as $import) {
            $byId[$import->id] = $import;
            $import->rowCounts = array_fill_keys(self::KEYS, 0);
        }
        if ($byId === []) {
            return;
        }

        $groups = DB::table('invitation_import_rows')
            ->whereIn('invitation_import_id', array_keys($byId))
            ->groupBy('invitation_import_id', 'outcome')
            ->select(['invitation_import_id', 'outcome', DB::raw('count(*) as total'), DB::raw('count(community_invitation_id) as invited')])
            // The rows that "Send the mails that were not sent" takes (InvitationImports::whereNotSent)
            ->selectRaw(
                'count(community_invitation_id) filter (where outcome in (?, ?) or (outcome = ? and queued_at < ?)) as not_sent',
                [KnowiiInvitationImportOutcome::Held->value, KnowiiInvitationImportOutcome::Failed->value, KnowiiInvitationImportOutcome::Queued->value, InvitationImports::staleQueuedBefore()],
            )
            ->get();
        foreach ($groups as $group) {
            $importId = $group->invitation_import_id ?? null;
            $import = is_numeric($importId) ? ($byId[(int) $importId] ?? null) : null;
            $outcome = KnowiiInvitationImportOutcome::tryFrom(is_string($group->outcome ?? null) ? $group->outcome : '');
            if ($import === null || $outcome === null || $import->rowCounts === null) {
                continue;
            }
            $counts = $import->rowCounts;
            $total = is_numeric($group->total ?? null) ? (int) $group->total : 0;
            $counts['rows'] += $total;
            $counts[$outcome->value] += $total;
            $counts['still_invited'] += is_numeric($group->invited ?? null) ? (int) $group->invited : 0;
            $counts['not_sent'] += is_numeric($group->not_sent ?? null) ? (int) $group->not_sent : 0;
            $import->rowCounts = $counts;
        }
    }
}
