<?php

namespace App\Mail\Concerns;

use App\Enums\KnowiiMailDeliveryStatus;
use App\Models\CommunityInvitation;
use App\Models\MailDelivery;
use App\Models\User;
use App\Support\MailKinds;
use Illuminate\Mail\Mailables\Headers;
use LogicException;
use Visus\Cuid2\Cuid2;

/**
 * The row of the delivery log of a mail (#1478, ADR 0054). The sender writes the row before the mail goes to the queue
 * (recordDelivery()), and the mail carries the header `X-Knowii-Delivery` with the cuid of the row: an opaque id, no
 * personal data. The listener of MessageSent reads it and stores the id that the mail provider gives the mail. The kind of
 * the row comes from App\Support\MailKinds.
 */
trait HasDeliveryRow
{
    /**
     * The cuid of the row of the delivery log, or null when the mail has none
     */
    public ?string $deliveryCuid = null;

    /**
     * Writes the row, `queued`, for the account or the invitation that the mail is for (neither: the alert of the operator)
     */
    public function recordDelivery(?User $user = null, ?CommunityInvitation $invitation = null): static
    {
        $kind = MailKinds::of(static::class) ?? throw new LogicException('The mail class '.static::class.' has no kind in App\Support\MailKinds');

        // The cuid is set here, not by the model event: the row is written also where model events are off
        $this->deliveryCuid = MailDelivery::query()->forceCreate([
            'cuid' => (new Cuid2(24))->toString(),
            'kind' => $kind,
            'user_id' => $user?->id,
            'community_invitation_id' => $invitation?->id,
            'channel' => MailDelivery::CHANNEL_EMAIL,
            'status' => KnowiiMailDeliveryStatus::Queued,
        ])->cuid;

        return $this;
    }

    public function headers(): Headers
    {
        return new Headers(text: $this->deliveryHeaders());
    }

    /**
     * @return array<string, string>
     */
    protected function deliveryHeaders(): array
    {
        return $this->deliveryCuid === null ? [] : [MailDelivery::HEADER => $this->deliveryCuid];
    }
}
