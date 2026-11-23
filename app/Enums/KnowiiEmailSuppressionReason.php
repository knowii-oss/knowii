<?php

namespace App\Enums;

/**
 * Why the notification email of an address stopped (#1478, ADR 0054): a hard bounce, a complaint (the member marked a mail
 * as spam), 3 soft bounces in a row, or the list of the mail provider (`email.suppressed`). The sign-in link and the
 * other mail that a person asks for still go.
 */
enum KnowiiEmailSuppressionReason: string
{
    case HardBounce = 'hard_bounce';
    case Complaint = 'complaint';
    case SoftBounces = 'soft_bounces';
    case ProviderList = 'provider_list';

    /**
     * A delivery to the address lifts these reasons. A complaint is lifted only by the member
     */
    public function isLiftedByADelivery(): bool
    {
        return $this !== self::Complaint;
    }

    /**
     * The reasons that also put the address on the list of the provider
     */
    public function isOnTheProviderList(): bool
    {
        return $this !== self::SoftBounces;
    }

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
