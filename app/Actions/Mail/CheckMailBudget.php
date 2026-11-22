<?php

namespace App\Actions\Mail;

use App\Constants;
use App\Contracts\Mail\ChecksMailBudgets;
use App\Contracts\Mail\ReadsMailUsage;
use App\Mail\MailBudgetAlert;
use App\Support\MailBudgetCheck;
use App\Support\MailSettings;
use App\Support\MailUsagePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Visus\Cuid2\Cuid2;

/**
 * The budget of the mail of the month (#1477, ADR 0054, design 6.14 point 12). All the state is on the row of the current
 * calendar month (UTC) in `mail_usage_months`: a new month has a new row, so its alerts and its hold start again.
 *
 * - The quota: MAIL_MONTHLY_QUOTA, else the monthly limit of the provider, else none (the check does nothing more).
 * - The use: the monthly count of the provider when it could be read (it sees the other senders of the same team), else
 *   the count of this instance (`sent_count`). A read of the provider is stored on the row (`provider_limit`,
 *   `provider_used`, `provider_sent_count`) for the checks of an import of invitations (#1390).
 * - At 80%, once a month: a warning in the log and the alert mail to MAIL_ALERT_TO.
 * - At the hold percent: notification mail is held until the provider starts the count again (else the first day of the
 *   next month), with a second alert once a month. The daily limit of the provider holds until the next day, with a log
 *   line and no mail (it would use the daily quota). Below the hold percent again, the hold ends.
 *
 * The alert mail is account mail (level 0, queue `default`): it is never held.
 */
final class CheckMailBudget implements ChecksMailBudgets
{
    public function __construct(private readonly ReadsMailUsage $reader) {}

    final public function check(): MailBudgetCheck
    {
        $now = CarbonImmutable::now('UTC');
        $row = $this->currentRow($now);

        $readsProvider = MailSettings::readsProviderUsage();
        $usage = $readsProvider ? $this->reader->read() : null;
        // The provider could not be read: the count of this instance can be lower (other senders of the same team)
        $readFailed = $readsProvider && $usage === null;
        $wasHeld = $row['held_until'] !== null && $row['held_until']->greaterThan($now);
        $quota = MailSettings::monthlyQuota() ?? self::positive($usage?->monthly->limit);
        $used = $usage !== null ? $usage->monthly->used : $row['sent_count'];
        $source = $usage !== null ? MailBudgetCheck::SOURCE_PROVIDER : MailBudgetCheck::SOURCE_LOCAL;

        if ($usage !== null) {
            // The checks of an import of invitations read the same quota and use until the next run (#1390)
            DB::table('mail_usage_months')->where('id', $row['id'])->update([
                'provider_limit' => self::positive($usage->monthly->limit),
                'provider_used' => max(0, $usage->monthly->used),
                'provider_sent_count' => $row['sent_count'],
                'updated_at' => $now,
            ]);
        }

        if ($quota === null) {
            return new MailBudgetCheck($used, null, null, $source, $row['held_until']);
        }

        $percent = intdiv($used * 100, $quota);
        $holdPercent = MailSettings::holdPercent();
        $numbers = ['month' => $row['month'], 'used' => $used, 'quota' => $quota, 'percent' => $percent, 'source' => $source, 'hold_percent' => $holdPercent];

        $alerted = false;
        if ($used * 100 >= $quota * Constants::MAIL_BUDGET_ALERT_PERCENT && $this->claim($row['id'], 'alert_80_sent_at', $now)) {
            Log::warning('The mail of the month reached '.Constants::MAIL_BUDGET_ALERT_PERCENT.'% of the quota', $numbers);
            $alerted = true;
        }

        $until = null;
        if ($used * 100 >= $quota * $holdPercent) {
            $until = self::resetTime($usage?->monthly, $now, $now->startOfMonth()->addMonthNoOverflow());
            if ($this->claim($row['id'], 'hold_notified_at', $now)) {
                Log::warning('The mail of the month reached the hold percent of the quota: notification mail is held', [...$numbers, 'held_until' => $until->toIso8601String()]);
                // One mail when both steps come in the same run: the one about the hold
                $this->sendAlert($row['month'], $used, $quota, $percent, $source, $holdPercent, $until);
                $alerted = false;
            } elseif (! $wasHeld) {
                // The alert of the month went out before: the log tells that the hold is back, with no second mail
                Log::warning('The mail of the month is at the hold percent of the quota again: notification mail is held', [...$numbers, 'held_until' => $until->toIso8601String()]);
            }
        }
        if ($alerted) {
            $this->sendAlert($row['month'], $used, $quota, $percent, $source, $holdPercent, null);
        }

        $daily = $usage?->daily;
        $dailyLimit = self::positive($daily?->limit);
        if ($daily !== null && $dailyLimit !== null && $daily->used * 100 >= $dailyLimit * $holdPercent) {
            $dailyUntil = self::resetTime($daily, $now, $now->startOfDay()->addDay());
            $until = $until === null || $dailyUntil->greaterThan($until) ? $dailyUntil : $until;
            if ($row['held_until'] === null || $row['held_until']->lessThan($dailyUntil)) {
                Log::warning('The mail of the day reached the hold percent of the daily limit of the provider: notification mail is held', ['used' => $daily->used, 'limit' => $dailyLimit, 'held_until' => $dailyUntil->toIso8601String()]);
            }
        }

        if ($until !== null) {
            DB::table('mail_usage_months')->where('id', $row['id'])->update(['held_until' => $until, 'updated_at' => $now]);
        } elseif ($row['held_until'] !== null && ! $readFailed) {
            // The numbers are below the hold percent again (the operator raised the quota, or a refusal set the hold).
            // Not after a failed read of the provider: one timeout must not lift a hold that its numbers gave
            DB::table('mail_usage_months')->where('id', $row['id'])->update(['held_until' => null, 'updated_at' => $now]);
            if ($wasHeld) {
                Log::info('The mail budget has room again: notification mail is not held any more', $numbers);
            }
        }

        return new MailBudgetCheck($used, $quota, $percent, $source, $until ?? ($readFailed && $wasHeld ? $row['held_until'] : null));
    }

    final public function isHeld(): bool
    {
        return $this->heldUntil() !== null;
    }

    final public function heldUntil(): ?CarbonImmutable
    {
        $now = CarbonImmutable::now('UTC');
        $heldUntil = DB::table('mail_usage_months')->where('month', $now->startOfMonth()->toDateString())->value('held_until');
        if (! is_string($heldUntil)) {
            return null;
        }
        $until = CarbonImmutable::parse($heldUntil, 'UTC');

        return $until->greaterThan($now) ? $until : null;
    }

    final public function holdUntil(CarbonImmutable $until, string $reason): void
    {
        $now = CarbonImmutable::now('UTC');
        $row = $this->currentRow($now);
        $until = $until->utc();

        // One statement, so that a shorter hold of another worker never replaces a longer one
        DB::table('mail_usage_months')
            ->where('id', $row['id'])
            ->where(static fn (Builder $query) => $query->whereNull('held_until')->orWhere('held_until', '<', $until))
            ->update(['held_until' => $until, 'updated_at' => $now]);

        Log::warning('The mail provider refused a mail for its quota: notification mail is held', ['reason' => $reason, 'held_until' => $until->toIso8601String()]);
    }

    /**
     * The row of the month of $now, created when it does not exist
     *
     * @return array{id: int, month: string, sent_count: int, held_until: CarbonImmutable|null}
     */
    private function currentRow(CarbonImmutable $now): array
    {
        $month = $now->startOfMonth()->toDateString();

        DB::table('mail_usage_months')->insertOrIgnore([
            'cuid' => (new Cuid2(24))->toString(),
            'month' => $month,
            'sent_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::table('mail_usage_months')->where('month', $month)->first(['id', 'sent_count', 'held_until']);
        $id = $row?->id;
        $sent = $row?->sent_count;
        $heldUntil = $row?->held_until;

        return [
            'id' => is_numeric($id) ? (int) $id : 0,
            'month' => $month,
            'sent_count' => is_numeric($sent) ? (int) $sent : 0,
            'held_until' => is_string($heldUntil) ? CarbonImmutable::parse($heldUntil, 'UTC') : null,
        ];
    }

    /**
     * Sets the time of a once-a-month step when it is not set yet. True for the run that set it: only that one acts
     */
    private function claim(int $id, string $column, CarbonImmutable $now): bool
    {
        return DB::table('mail_usage_months')->where('id', $id)->whereNull($column)->update([$column => $now, 'updated_at' => $now]) === 1;
    }

    /**
     * The alert mail to each valid address of MAIL_ALERT_TO. Without one, the log line is the alert
     */
    private function sendAlert(string $month, int $used, int $quota, int $percent, string $source, int $holdPercent, ?CarbonImmutable $heldUntil): void
    {
        foreach (MailSettings::alertTo() as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                Log::warning('An address of MAIL_ALERT_TO is not a valid email address: it gets no alert');

                continue;
            }

            try {
                Mail::to($address)->queue(new MailBudgetAlert(CarbonImmutable::parse($month, 'UTC'), $used, $quota, $percent, $source, $holdPercent, $heldUntil));
            } catch (Throwable $exception) {
                Log::error('The mail budget alert could not be queued', ['exception' => $exception::class]);
            }
        }
    }

    /**
     * When the count of the provider starts again, when it is given and in the future, else the fallback
     */
    private static function resetTime(?MailUsagePeriod $period, CarbonImmutable $now, CarbonImmutable $fallback): CarbonImmutable
    {
        $resetsAt = $period?->resetsAt;

        return $resetsAt !== null && $resetsAt->greaterThan($now) ? $resetsAt->utc() : $fallback;
    }

    private static function positive(?int $value): ?int
    {
        return $value !== null && $value > 0 ? $value : null;
    }
}
