<?php

namespace App\Listeners;

use App\Contracts\Mail\RemovesProviderSuppressions;
use App\Enums\KnowiiMailKind;
use App\Support\EmailSuppression;
use App\Support\MailKinds;
use App\Support\MailSettings;
use App\Support\ProviderRemovals;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * A mail that a person asks for is always tried (#1478, ADR 0054, groundwork 7.2): the sign-in link, the password reset,
 * the address check, the confirmation of a new address, the two mails of the sign-up form and the mail of a change to an
 * address that has an account (ADR 0065, ADR 0058) (App\Support\MailKinds, kind `asked_for`, also for the two
 * notifications of the framework). Before such a mail goes through Resend to an address that the platform suppressed
 * for a reason that the provider lists too (a hard bounce, a complaint, the list of the provider), the provider is asked
 * to take the address off its list, within the cap of App\Support\ProviderRemovals. Above the cap, or when the removal
 * fails, the mail still goes, and a log line says why. The row of the platform stays: notification mail stays off.
 *
 * With `smtp` or `log`, nothing calls the provider. It never throws: the mail must go.
 */
class TakeAddressOffProviderList
{
    public function __construct(private readonly RemovesProviderSuppressions $removals) {}

    final public function handleMessageSending(MessageSending $event): void
    {
        try {
            if (! MailSettings::sendsThroughResend()) {
                return;
            }
            $class = $event->data['__laravel_mailable'] ?? $event->data['__laravel_notification'] ?? null;
            if (MailKinds::of(is_string($class) ? $class : null) !== KnowiiMailKind::AskedFor) {
                return;
            }

            foreach ($event->message->getTo() as $address) {
                $this->takeOff($address);
            }
        } catch (Throwable $exception) {
            Log::error('The removal from the list of the mail provider failed before a mail that a person asked for: the mail still goes', ['exception' => $exception::class]);
        }
    }

    private function takeOff(Address $address): void
    {
        $email = mb_strtolower($address->getAddress());
        if (! EmailSuppression::isOnTheProviderList($email)) {
            return;
        }
        if (! ProviderRemovals::attempt($email)) {
            Log::info('An address is not taken off the list of the mail provider before a mail that a person asked for: the cap of the removals is reached. The mail still goes');

            return;
        }

        $this->removals->remove($email)
            ? Log::info('An address was taken off the list of the mail provider before a mail that a person asked for')
            : Log::info('An address could not be taken off the list of the mail provider before a mail that a person asked for: the mail still goes');
    }
}
