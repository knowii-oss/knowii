<?php

namespace App\Support;

use App\Enums\KnowiiMailKind;
use App\Mail\CommunityInvitation;
use App\Mail\CommunityOwnershipTransferred;
use App\Mail\ConfirmNewEmailAddress;
use App\Mail\EmailAddressChanged;
use App\Mail\EmailAddressHasAccount;
use App\Mail\FinishSignUp;
use App\Mail\MagicLink;
use App\Mail\MailBudgetAlert;
use App\Mail\NotificationMail;
use App\Mail\SignUpAccountExists;
use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Auth\Notifications\ResetPassword;

/**
 * The one map from the class of a mail to its kind (#1478, ADR 0054): the kind of its row of the delivery log, and what a
 * suppressed address means for it. A notification mail (NotificationMail and its subclasses) has the kind of its group,
 * read from the mail. `tests/Feature/MailDeliveryTest.php` fails for a class of `app/Mail` or `app/Notifications` that the
 * map does not name.
 */
final class MailKinds
{
    /**
     * Null: the kind is the group of the mail (a notification mail)
     *
     * @var array<class-string, KnowiiMailKind|null>
     */
    public const array CLASSES = [
        NotificationMail::class => null,
        // A person asked for it a moment ago: always handed to the mailer, after a removal from the list of the provider
        MagicLink::class => KnowiiMailKind::AskedFor,
        ConfirmNewEmailAddress::class => KnowiiMailKind::AskedFor,
        VerifyEmailAddress::class => KnowiiMailKind::AskedFor,
        ResetPassword::class => KnowiiMailKind::AskedFor,
        ResetPasswordLink::class => KnowiiMailKind::AskedFor,
        // The two mails of the sign-up form and the mail of a change to an address that has an account (ADR 0065, ADR 0058):
        // a person asked for them a moment ago, as for a sign-in link
        FinishSignUp::class => KnowiiMailKind::AskedFor,
        SignUpAccountExists::class => KnowiiMailKind::AskedFor,
        EmailAddressHasAccount::class => KnowiiMailKind::AskedFor,
        // A manager asked for it, not the person who gets it
        CommunityInvitation::class => KnowiiMailKind::Invitation,
        // About the account, not asked for
        CommunityOwnershipTransferred::class => KnowiiMailKind::Account,
        EmailAddressChanged::class => KnowiiMailKind::Account,
        // To the operator, never to a member
        MailBudgetAlert::class => KnowiiMailKind::Operator,
    ];

    /**
     * The kind of a mail class that is not a notification mail, or null (a notification mail, or a class that the map does
     * not name)
     */
    public static function of(?string $class): ?KnowiiMailKind
    {
        if ($class === null || $class === '') {
            return null;
        }
        if (array_key_exists($class, self::CLASSES)) {
            return self::CLASSES[$class];
        }
        foreach (self::CLASSES as $named => $kind) {
            if ($kind !== null && is_subclass_of($class, $named)) {
                return $kind;
            }
        }

        return null;
    }
}
