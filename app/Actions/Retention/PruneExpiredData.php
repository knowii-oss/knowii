<?php

namespace App\Actions\Retention;

use App\Actions\Communities\ExportCommunity;
use App\Contracts\Retention\PrunesExpiredData;
use App\Enums\KnowiiInvitationImportState;
use App\Models\CommunityInvitation;
use App\Models\Event;
use App\Models\EventAnswer;
use App\Models\InvitationImport;
use App\Models\Notification;
use App\Models\User;
use App\Support\InvitationMailCaps;
use App\Support\Retention;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Deletes the data that Knowii keeps only for a time (ADR 0030, #1306), with the periods of App\Support\Retention:
 *
 * - community invitations, some days after they expire
 * - imports of invitations from a file (#1390): a preview that nobody sent some hours after the upload, a sent import as
 *   long as an invitation lives after its confirm
 * - the count of the daily caps of the invitation mails (#1390): a row 24 hours after its mail went to the queue
 * - personal API tokens, some days after their expiry date
 * - failed background jobs (their payload can hold personal data)
 * - password reset tokens after their validity
 * - sessions (IP address, browser) after the session lifetime, when they are stored in the database: Laravel
 *   deletes them only at random on some requests
 * - notifications (#1474): the rows that the member read, some days after they read them, and the rows that they never read,
 *   some days after they were written
 * - answers of members to events (#1515, ADR 0053), some days after the end of their event
 * - community exports (zip files of `php artisan communities:export`, #1353) after their retention period
 * - a new email address that waits for its confirmation (#1706, ADR 0058), some days after the request: the address and
 *   the time of the request are cleared, the account stays
 * - cache entries after their expiry (sign-in link tokens, rate limit counters by IP address), when the cache is the
 *   database: an expired entry stays in the table until someone reads its key again
 */
final class PruneExpiredData implements PrunesExpiredData
{
    final public function prune(bool $dryRun = false): array
    {
        $counts = [
            'invitations' => $this->apply(
                CommunityInvitation::query()->where('created_at', '<', now()->subDays(Retention::invitationValidityDays() + Retention::expiredInvitationsDays()))->toBase(),
                $dryRun
            ),
            // A preview that nobody sent, and the record of a sent import as long as an invitation lives (#1390, ADR 0061). The
            // rows go by the cascade
            'invitation_imports' => $this->apply(
                InvitationImport::query()
                    ->where(fn ($query) => $query
                        ->where(fn ($preview) => $preview->where('state', KnowiiInvitationImportState::Preview->value)->where('created_at', '<', now()->subHours(Retention::invitationImportPreviewHours())))
                        ->orWhere('confirmed_at', '<', now()->subDays(Retention::invitationImportsDays())))
                    ->toBase(),
                $dryRun
            ),
            // The count of the daily caps of the invitation mails (#1390, ADR 0061): a row that counts no more
            'invitation_mails' => $this->apply(
                DB::table('invitation_mails')->where('created_at', '<', now()->subHours(InvitationMailCaps::WINDOW_HOURS)),
                $dryRun
            ),
            'api_tokens' => $this->apply(
                PersonalAccessToken::query()->whereNotNull('expires_at')->where('expires_at', '<', now()->subDays(Retention::expiredApiTokensDays()))->toBase(),
                $dryRun
            ),
            'failed_jobs' => $this->apply(
                $this->table(config('queue.failed.database'), config('queue.failed.table'), 'failed_jobs')
                    ->where('failed_at', '<', now()->subDays(Retention::failedJobsDays())),
                $dryRun
            ),
            'password_reset_tokens' => $this->apply(
                $this->table(null, config('auth.passwords.users.table'), 'password_reset_tokens')
                    ->where('created_at', '<', now()->subMinutes(Retention::passwordResetMinutes())),
                $dryRun
            ),
            'sessions' => config('session.driver') === 'database'
                ? $this->apply(
                    $this->table(config('session.connection'), config('session.table'), 'sessions')
                        ->where('last_activity', '<', now()->subMinutes(Retention::sessionMinutes())->getTimestamp()),
                    $dryRun
                )
                : 0,
            'cache' => $this->pruneCache($dryRun),
            // Read rows by their read date, unread rows by their creation date (ADR 0042)
            'notifications' => $this->apply(
                Notification::query()
                    ->where(fn ($query) => $query
                        ->where('read_at', '<', now()->subDays(Retention::notificationsReadDays()))
                        ->orWhere(fn ($unread) => $unread->whereNull('read_at')->where('created_at', '<', now()->subDays(Retention::notificationsUnreadDays()))))
                    ->toBase(),
                $dryRun
            ),
            // An answer says what a member planned to do: it has no use long after the event (ADR 0053)
            'event_answers' => $this->apply(
                EventAnswer::query()
                    ->whereIn('event_id', Event::query()->where('ends_at', '<', now()->subDays(Retention::eventAnswersDays()))->select('id'))
                    ->toBase(),
                $dryRun
            ),
            'exports' => $this->pruneExports($dryRun),
            'pending_emails' => $this->prunePendingEmails($dryRun),
        ];

        Log::info($dryRun ? 'Expired data found' : 'Expired data deleted', $counts);

        return $counts;
    }

    /**
     * The export files are copies of personal data: they stay only for a few days (data rule 8)
     */
    private function pruneExports(bool $dryRun): int
    {
        $disk = Storage::disk(ExportCommunity::DISK);
        $limit = now()->subDays(Retention::exportsDays())->getTimestamp();
        $deleted = 0;

        foreach ($disk->files(ExportCommunity::DIRECTORY) as $file) {
            if ($disk->lastModified($file) >= $limit) {
                continue;
            }
            if ($dryRun || $disk->delete($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * A change of the email address that was never confirmed (#1706): the account keeps its address, and the pending one goes
     */
    private function prunePendingEmails(bool $dryRun): int
    {
        $query = User::query()
            ->where(fn ($pending) => $pending->whereNotNull('pending_email')->orWhereNotNull('pending_email_requested_at'))
            ->where(fn ($old) => $old->whereNull('pending_email_requested_at')->orWhere('pending_email_requested_at', '<=', now()->subDays(Retention::pendingEmailDays())))
            ->toBase();

        return $dryRun ? $query->count() : $query->update(['pending_email' => null, 'pending_email_requested_at' => null]);
    }

    private function pruneCache(bool $dryRun): int
    {
        $store = config('cache.default');
        if (! is_string($store) || config("cache.stores.{$store}.driver") !== 'database') {
            return 0;
        }

        $connection = config("cache.stores.{$store}.connection");
        $table = config("cache.stores.{$store}.table");
        $lockConnection = config("cache.stores.{$store}.lock_connection") ?? $connection;
        $lockTable = config("cache.stores.{$store}.lock_table");
        $now = now()->getTimestamp();

        $entries = $this->apply($this->table($connection, $table, 'cache')->where('expiration', '<', $now), $dryRun);
        $locks = $this->apply($this->table($lockConnection, $lockTable, 'cache_locks')->where('expiration', '<', $now), $dryRun);

        return $entries + $locks;
    }

    private function table(mixed $connection, mixed $table, string $default): Builder
    {
        return DB::connection(is_string($connection) && $connection !== '' ? $connection : null)
            ->table(is_string($table) && $table !== '' ? $table : $default);
    }

    private function apply(Builder $query, bool $dryRun): int
    {
        return $dryRun ? $query->count() : $query->delete();
    }
}
