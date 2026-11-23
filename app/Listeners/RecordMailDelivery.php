<?php

namespace App\Listeners;

use App\Enums\KnowiiMailDeliveryStatus;
use App\Models\MailDelivery;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Links a sent mail to its row of the delivery log (#1478, ADR 0054): the row that the header `X-Knowii-Delivery` names
 * gets `sent`, the time, and the id that the mail provider gave the mail. The id is read from the header
 * `X-Resend-Email-ID`, which the Resend transport adds after the send, never from the message id: the other transports
 * (`smtp`, `log`, `array`) give their own id, and the column then stays null.
 *
 * It never throws: the mail is already sent, and an exception here would retry the job and send it twice.
 */
class RecordMailDelivery
{
    private const string PROVIDER_ID_HEADER = 'X-Resend-Email-ID';

    final public function handleMessageSent(MessageSent $event): void
    {
        try {
            $headers = $event->message->getHeaders();
            $cuid = $headers->get(MailDelivery::HEADER)?->getBodyAsString();
            if ($cuid === null || $cuid === '') {
                return;
            }
            $providerId = $headers->get(self::PROVIDER_ID_HEADER)?->getBodyAsString();

            MailDelivery::query()->where('cuid', $cuid)->update([
                'status' => KnowiiMailDeliveryStatus::Sent->value,
                'sent_at' => now(),
                'provider_email_id' => $providerId !== null && $providerId !== '' ? $providerId : null,
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('A sent mail could not be written in the delivery log: the mail is sent, a report of the provider on it falls back to the address', ['exception' => $exception::class]);
        }
    }
}
