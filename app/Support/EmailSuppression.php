<?php

namespace App\Support;

use App\Enums\KnowiiEmailSuppressionReason;
use App\Models\EmailSuppression as EmailSuppressionRow;
use App\Models\User;

/**
 * The reader of the suppression of the notification email (#1478, ADR 0054): no other code reads `email_suppressions`
 * to decide. A row applies only while it is suppressed and its address is the address of the account (without the case),
 * so a change of the address lifts it at once. A row that only counts soft bounces does not apply.
 */
final class EmailSuppression
{
    /**
     * The notification email of the account is suppressed
     */
    public static function appliesTo(User $user): bool
    {
        return self::rowFor($user) !== null;
    }

    /**
     * The suppressed row of the current address of the account, or null
     */
    public static function rowFor(User $user): ?EmailSuppressionRow
    {
        $row = EmailSuppressionRow::query()->where('user_id', $user->id)->whereNotNull('suppressed_at')->first();

        return $row !== null && $row->email === mb_strtolower($user->email) ? $row : null;
    }

    /**
     * The address is suppressed for a reason that also puts it on the list of the mail provider (a hard bounce, a
     * complaint, the list of the provider): the provider skips every mail to it until it is taken off
     */
    public static function isOnTheProviderList(string $address): bool
    {
        return EmailSuppressionRow::query()
            ->where('email', mb_strtolower(trim($address)))
            ->whereNotNull('suppressed_at')
            ->whereIn('reason', [KnowiiEmailSuppressionReason::HardBounce->value, KnowiiEmailSuppressionReason::Complaint->value, KnowiiEmailSuppressionReason::ProviderList->value])
            ->exists();
    }
}
