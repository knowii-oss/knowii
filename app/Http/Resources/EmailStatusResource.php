<?php

namespace App\Http\Resources;

use App\Enums\KnowiiNotificationEmailGroup;
use App\Models\NotificationEmailChoice;
use App\Models\User;
use App\Support\EmailSuppression;
use Illuminate\Http\Request;

/**
 * The email status of an account (#1478, ADR 0054), for the account only: a view of the user, not its profile.
 *
 * @mixin User
 */
class EmailStatusResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with emailStatusSchema (email-status.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $row = EmailSuppression::rowFor($user);
        $choices = NotificationEmailChoice::query()->where('user_id', $user->id)->pluck('state', 'group');

        return [
            'suppressed' => $row !== null,
            'reason' => $row?->reason?->value,
            'since' => $row?->suppressed_at,
            'address_hint' => $user->email,
            'email_choices' => array_map(static fn (KnowiiNotificationEmailGroup $group): array => [
                'group' => $group->value,
                'state' => is_string($state = $choices->get($group->value)) ? $state : NotificationEmailChoice::STATE_DEFAULT,
            ], KnowiiNotificationEmailGroup::cases()),
        ];
    }
}
