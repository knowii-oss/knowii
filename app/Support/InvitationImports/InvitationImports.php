<?php

namespace App\Support\InvitationImports;

use App\Constants;
use App\Enums\KnowiiInvitationImportOutcome;
use App\Enums\KnowiiInvitationImportState;
use App\Events\InvitationImports\InvitationImportChanged;
use App\Jobs\SendInvitationImportMail;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\InvitationImport;
use App\Models\InvitationImportRow;
use App\Models\MemberTag;
use App\Support\Retention;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The rules of the life of an import of invitations that more than one place reads (#1390, ADR 0061): how long a preview
 * and a record stay, when "Invite again the people who did not join" opens, the jobs of the mails and the live update.
 */
final class InvitationImports
{
    /**
     * A preview older than the preview period does not exist any more, also before the prune step ran
     */
    public static function isExpiredPreview(InvitationImport $import): bool
    {
        return $import->state === KnowiiInvitationImportState::Preview
            && $import->created_at->lessThan(now()->subHours(Retention::invitationImportPreviewHours()));
    }

    /**
     * The imports of the community that exist for a reader: an expired preview is left out
     *
     * @return HasMany<InvitationImport, covariant Community>
     */
    public static function listed(Community $community): HasMany
    {
        return $community->invitationImports()->where(static fn ($query) => $query
            ->where('state', '!=', KnowiiInvitationImportState::Preview->value)
            ->orWhere('created_at', '>=', now()->subHours(Retention::invitationImportPreviewHours())));
    }

    /**
     * @throws ModelNotFoundException<InvitationImport>
     */
    public static function assertAvailable(InvitationImport $import): void
    {
        if (self::isExpiredPreview($import)) {
            throw (new ModelNotFoundException)->setModel(InvitationImport::class, [$import->cuid]);
        }
    }

    /**
     * The end of the preview, or the day when the record of a sent import is deleted
     */
    public static function keptUntil(InvitationImport $import): CarbonImmutable
    {
        if ($import->confirmed_at === null) {
            return CarbonImmutable::instance($import->created_at)->addHours(Retention::invitationImportPreviewHours());
        }

        return CarbonImmutable::instance($import->confirmed_at)->addDays(Retention::invitationImportsDays());
    }

    /**
     * From when "Invite again the people who did not join" can run: null before the confirm and after it ran once
     */
    public static function resendNotJoinedFrom(InvitationImport $import): ?CarbonImmutable
    {
        if ($import->confirmed_at === null || $import->resent_at !== null) {
            return null;
        }

        return CarbonImmutable::instance($import->confirmed_at)->addDays(Constants::INVITATION_IMPORT_RESEND_AFTER_DAYS);
    }

    /**
     * The rows whose mail did not go out, that "Send the mails that were not sent" takes: `held`, `failed`, and `queued`
     * before staleQueuedBefore() (the time of the retries of its mail and of its last attempt is over). A row that goes
     * to the queue again gets a new `queued_count`: a job of an earlier time changes nothing
     *
     * @param  Builder<InvitationImportRow>  $query
     */
    public static function whereNotSent(Builder $query): void
    {
        $query->where(static fn ($rows) => $rows
            ->whereIn('outcome', [KnowiiInvitationImportOutcome::Held->value, KnowiiInvitationImportOutcome::Failed->value])
            ->orWhere(static fn ($stale) => $stale
                ->where('outcome', KnowiiInvitationImportOutcome::Queued->value)
                ->where('queued_at', '<', self::staleQueuedBefore())));
    }

    /**
     * A row queued before this time counts as not sent: Constants::MAIL_NOTIFICATION_RETRY_HOURS (the job is queued
     * after the row, so it is tried until a bit later), plus Constants::MAIL_JOB_TIMEOUT_SECONDS (an attempt that
     * started just before the end of the retries), plus Constants::INVITATION_IMPORT_STALE_MARGIN_MINUTES. So no job of
     * the row sends any more when a send again takes it. The confirm, the send again, the counts and the delete of a
     * record read it
     */
    public static function staleQueuedBefore(): CarbonImmutable
    {
        return CarbonImmutable::now()
            ->subHours(Constants::MAIL_NOTIFICATION_RETRY_HOURS)
            ->subSeconds(Constants::MAIL_JOB_TIMEOUT_SECONDS)
            ->subMinutes(Constants::INVITATION_IMPORT_STALE_MARGIN_MINUTES);
    }

    /**
     * One job per row, after the commit of the caller: each holds the id of its row and the `queued_count` that the row
     * got now, never the address
     *
     * @param  array<int, int>  $rows  the `queued_count` of each row, by the id of the row
     */
    public static function queueMails(array $rows): void
    {
        foreach ($rows as $rowId => $queuedCount) {
            SendInvitationImportMail::dispatch($rowId, $queuedCount)->afterCommit();
        }
    }

    /**
     * The live update of the person who uploaded the file, when the account still exists
     */
    public static function notify(InvitationImport $import, string $communityCuid): void
    {
        $user = $import->user()->first(['id', 'cuid']);
        if ($user !== null) {
            InvitationImportChanged::dispatch($user->cuid, $communityCuid, $import->cuid);
        }
    }

    /**
     * Ready a page of imports for the resource, with a fixed number of queries: the counts (one grouped query), the
     * person who uploaded each, and the default groups and tags that still exist, by name (one query each)
     *
     * @param  iterable<InvitationImport>  $imports
     */
    public static function prepare(iterable $imports, Community $community): void
    {
        $items = [];
        foreach ($imports as $import) {
            $items[] = $import;
        }
        if ($items === []) {
            return;
        }
        $list = new Collection($items);
        InvitationImportCounts::attach($list);
        $list->load('user');

        $groupCuids = $list->flatMap(static fn (InvitationImport $import): array => self::strings($import->default_access_groups))->unique()->values()->all();
        $tagCuids = $list->flatMap(static fn (InvitationImport $import): array => self::strings($import->default_member_tags))->unique()->values()->all();
        $groups = $groupCuids === [] ? new Collection : AccessGroup::query()->where('community_id', $community->id)->whereIn('cuid', $groupCuids)->orderByRaw('lower(name)')->orderBy('id')->get();
        $tags = $tagCuids === [] ? new Collection : MemberTag::query()->where('community_id', $community->id)->whereIn('cuid', $tagCuids)->orderByRaw('lower(name)')->orderBy('id')->get();
        foreach ($list as $import) {
            $chosenGroups = self::strings($import->default_access_groups);
            $chosenTags = self::strings($import->default_member_tags);
            $import->setRelation('defaultAccessGroups', $groups->filter(static fn (AccessGroup $group): bool => in_array($group->cuid, $chosenGroups, true))->values());
            $import->setRelation('defaultMemberTags', $tags->filter(static fn (MemberTag $tag): bool => in_array($tag->cuid, $chosenTags, true))->values());
        }
    }

    /**
     * @param  array<array-key, string>  $list
     * @return list<string>
     */
    private static function strings(array $list): array
    {
        return array_values(array_filter($list, is_string(...)));
    }
}
