<?php

namespace App\Support;

use App\Constants;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The daily cap of the account mails per recipient: an address gets Constants::ACCOUNT_MAILS_PER_ADDRESS_PER_DAY mails of
 * each kind in 24 hours, whatever the IP address, the account or the form that asks for them. After the last of them, one
 * more mail of that kind goes at most once every Constants::ACCOUNT_MAILS_OVER_THE_CAP_GAP_HOURS hours, so the member waits
 * at most that time for a link. The kinds: the sign-in link, the sign-up mail (both mails of the sign-up together), the
 * password reset mail and the mail of a change of the address (the link and "This address already has an account"
 * together).
 *
 * The queued job of each form takes a place just before it sends, so the answer of the form never changes: a mail over the
 * cap is not sent. The counts are kept under an HMAC of the address with the key of the application, never the address.
 */
final class AccountMailCaps
{
    public const string SIGN_IN_LINK = 'sign-in-link';

    public const string SIGN_UP = 'sign-up';

    public const string PASSWORD_RESET = 'password-reset';

    public const string EMAIL_CHANGE = 'email-change';

    /**
     * Takes a place of the cap of the day of this kind for the address: false when the address got all its mails of this
     * kind for the day and the gap after the last one is not over, and the mail must not go
     */
    public static function take(string $kind, #[\SensitiveParameter] string $address): bool
    {
        $count = RateLimiter::increment(self::key('account-mails', $kind, $address), 86400);
        $gapKey = self::key('account-mails-gap', $kind, $address);
        $gapSeconds = Constants::ACCOUNT_MAILS_OVER_THE_CAP_GAP_HOURS * 3600;

        if ($count < Constants::ACCOUNT_MAILS_PER_ADDRESS_PER_DAY) {
            return true;
        }
        if ($count === Constants::ACCOUNT_MAILS_PER_ADDRESS_PER_DAY) {
            // The last mail of the cap: the gap begins with it
            RateLimiter::clear($gapKey);
            RateLimiter::increment($gapKey, $gapSeconds);

            return true;
        }

        // Over the cap: the first request after the gap takes the one mail, and begins the next gap
        return RateLimiter::increment($gapKey, $gapSeconds) === 1;
    }

    private static function key(string $prefix, string $kind, #[\SensitiveParameter] string $address): string
    {
        return $prefix.':'.$kind.':'.hash_hmac('sha256', 'account-mails:'.$kind.':'.mb_strtolower(trim($address)), config()->string('app.key'));
    }
}
