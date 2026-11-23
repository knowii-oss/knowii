<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * One report of the mail provider that the webhook accepted (#1478, ADR 0054), as the job reads it: what happened, to which
 * mail (`providerEmailId`) and to which address (in lower case). It holds an address, so its job is encrypted in the queue.
 */
final readonly class MailEvent
{
    public const string HARD_BOUNCE = 'hard_bounce';

    public const string SOFT_BOUNCE = 'soft_bounce';

    public const string COMPLAINT = 'complaint';

    public const string PROVIDER_LIST = 'provider_list';

    public const string DELIVERED = 'delivered';

    /**
     * @param  string  $what  One of the constants
     * @param  string  $type  The type of the event of the provider (`email.bounced`), for the log
     * @param  string  $eventCuid  The cuid of the row of `mail_webhook_events`, for the log
     */
    public function __construct(
        public string $what,
        public string $type,
        public string $eventCuid,
        public string $providerEmailId,
        public string $address,
        public CarbonImmutable $occurredAt,
        // The mail came from the sender address of this instance (`data.from`)
        public bool $fromThisInstance = false,
    ) {}

    /**
     * A hard bounce, a complaint and the list of the provider stop the notification email at once
     */
    public function suppresses(): bool
    {
        return in_array($this->what, [self::HARD_BOUNCE, self::COMPLAINT, self::PROVIDER_LIST], true);
    }
}
