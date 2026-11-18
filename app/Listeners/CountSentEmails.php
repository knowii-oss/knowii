<?php

namespace App\Listeners;

use App\Contracts\Mail\RecordsSentEmails;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Counts every mail that the transport accepted (#1477, ADR 0054): each recipient of the envelope (to, cc and bcc), whatever
 * the mailer, the queue or the kind of mail. A refused send fires no event, so it counts nothing. The count feeds the
 * budget check when the provider gives no usage.
 *
 * It never throws: the mail is already sent, and an exception here would retry the job and send it twice.
 */
class CountSentEmails
{
    public function __construct(private readonly RecordsSentEmails $recorder) {}

    final public function handleMessageSent(MessageSent $event): void
    {
        try {
            $recipients = count($event->sent->getSymfonySentMessage()->getEnvelope()->getRecipients());
            $this->recorder->record($recipients);
        } catch (Throwable $exception) {
            Log::error('The count of sent mail failed: the mail is sent, the count of the month misses it', ['exception' => $exception::class]);
        }
    }
}
