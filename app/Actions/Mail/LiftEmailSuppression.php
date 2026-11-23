<?php

namespace App\Actions\Mail;

use App\Contracts\Mail\LiftsEmailSuppressions;
use App\Contracts\Mail\RemovesProviderSuppressions;
use App\Models\EmailSuppression;
use App\Models\User;
use App\Support\MailSettings;
use App\Support\ProviderRemovals;
use Illuminate\Support\Facades\Log;

/**
 * "Try this address again" (#1478, ADR 0054). The route allows it once an hour per account (the limiter
 * `mail-suppression`). The removal at the provider is best effort, within the cap of App\Support\ProviderRemovals, and only
 * when the instance sends through the provider and the row had a reason that the provider lists: a failure only logs. If
 * the next mail bounces, the webhook suppresses the address again.
 */
final class LiftEmailSuppression implements LiftsEmailSuppressions
{
    public function __construct(private readonly RemovesProviderSuppressions $removals) {}

    final public function lift(User $user): void
    {
        $row = EmailSuppression::query()->where('user_id', $user->id)->first();
        if ($row === null) {
            return;
        }

        $row->delete();
        Log::info('The member lifted the suppression of the notification email', ['user' => $user->cuid, 'reason' => $row->reason?->value]);

        if ($row->suppressed_at === null || $row->email !== mb_strtolower($user->email) || $row->reason?->isOnTheProviderList() !== true || ! MailSettings::sendsThroughResend()) {
            return;
        }
        if (! ProviderRemovals::attempt($row->email)) {
            Log::info('The address is not taken off the list of the mail provider: the cap of the removals is reached', ['user' => $user->cuid]);

            return;
        }
        $this->removals->remove($row->email);
    }
}
