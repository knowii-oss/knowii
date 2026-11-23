<?php

namespace App\Contracts\Mail;

use App\Exceptions\MailWebhookException;
use App\Support\MailEvent;

/**
 * The webhook of the mail provider (#1478, ADR 0054): bounces, complaints, the list of the provider and the deliveries.
 */
interface ReceivesMailWebhooks
{
    /**
     * Checks a call (App\Support\MailWebhookSignature), keeps the id of its event, and queues the work of an event that
     * is new and that the platform reads. Does nothing more for an event that was handled, an event type that the platform
     * ignores, or a payload that names no mail or not exactly one address.
     *
     * @param  string  $key  The key of the secret of the instance (MailWebhookSignature::secret())
     * @param  string  $payload  The raw body of the request
     *
     * @throws MailWebhookException
     */
    public function receive(string $key, string $payload, ?string $id, ?string $timestamp, ?string $signature): void;

    /**
     * The work of one event, in the queued job: finds the mail that it is about (the delivery log, else the address), and
     * changes the suppression of the account or the bounce state of the invitation.
     */
    public function handle(MailEvent $event): void;
}
