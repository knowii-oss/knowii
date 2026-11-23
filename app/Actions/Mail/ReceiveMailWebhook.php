<?php

namespace App\Actions\Mail;

use App\Constants;
use App\Contracts\Mail\ReceivesMailWebhooks;
use App\Enums\KnowiiEmailSuppressionReason;
use App\Enums\KnowiiInvitationBounceType;
use App\Enums\KnowiiMailKind;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Jobs\HandleMailWebhookEvent;
use App\Models\CommunityInvitation;
use App\Models\EmailSuppression;
use App\Models\MailDelivery;
use App\Models\MailWebhookEvent;
use App\Models\User;
use App\Support\MailEvent;
use App\Support\MailWebhookSignature;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Throwable;
use Visus\Cuid2\Cuid2;

/**
 * The webhook of the mail provider (#1478, ADR 0054, design 6.13).
 *
 * The route part (receive()): the signature check, then, and only then, the id of the event goes to `mail_webhook_events`
 * with `insert ... on conflict do nothing`, in one transaction with the push of the job (the queue is the database), so a
 * failed push keeps no id. When no row was added, the event was handled before: nothing more. Else the work of an event
 * that the platform reads goes to a queued job:
 *
 * - `email.bounced` with `bounce.type` `Permanent`: a hard bounce. `Transient`, `Undetermined` and `Temporary`: a soft
 *   bounce. Any other or missing value is a soft bounce too, with a log line: a wrong suppression is worse than a late one.
 * - `email.complained`: a complaint. `email.suppressed`: the list of the provider, handled as a hard bounce.
 * - `email.delivered`: the address works again (the count of soft bounces, the lift of a suppression).
 * - Any other type, or a payload without `data.email_id` or without exactly one address in `data.to`: nothing.
 *
 * The job part (handle()) finds the mail through the delivery log (`provider_email_id`):
 *
 * - an invitation (also a deleted one): its bounce state, never an account;
 * - the alert of the operator: nothing;
 * - a mail of an account: the account, when the address of the report is the address of the account;
 * - no row: only a mail from the sender address of this instance (`mail.from.address`: the webhook belongs to the whole
 *   team at the provider), then the account with the address of the report, compared without the case, only when no
 *   invitation holds the same address (then nothing, and a log line).
 *
 * The suppression of an account: a hard bounce, a complaint and the list of the provider suppress at once (a complaint
 * replaces another reason, so that only the member lifts it; another report on a suppressed address changes nothing).
 * Constants::EMAIL_SOFT_BOUNCES_TO_SUPPRESS soft bounces in a row suppress too. A delivery puts the count back to 0, and
 * lifts a suppression that is not a complaint, unless the delivery is older than the last report of the row. A row about
 * another address (an address of before) is replaced.
 *
 * The log holds the event type and cuids, never an address, a header, the secret or the body.
 */
final class ReceiveMailWebhook implements ReceivesMailWebhooks
{
    private const int MAX_LENGTH_TYPE = 64;

    private const int MAX_LENGTH_EMAIL_ID = 255;

    private const array SOFT_BOUNCE_TYPES = ['Transient', 'Undetermined', 'Temporary'];

    final public function receive(string $key, string $payload, ?string $id, ?string $timestamp, ?string $signature): void
    {
        $eventId = MailWebhookSignature::verify($key, $payload, $id, $timestamp, $signature);

        // A JSON document: each value is narrowed where it is read
        $decoded = json_decode($payload, true);
        /** @var array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|null>|null>|null>|null>|null> $data */
        $data = is_array($decoded) ? $decoded : [];
        $type = is_string($data['type'] ?? null) ? mb_substr($data['type'], 0, self::MAX_LENGTH_TYPE) : '';

        $cuid = (new Cuid2(24))->toString();
        $event = self::eventOf($data, $type, $cuid);

        // The id and the job go together (the queue is the database): when the push of the job fails, the id is not kept
        // either, the answer is an error, and the retry of the provider handles the event
        DB::transaction(static function () use ($cuid, $eventId, $type, $event): void {
            $now = now();
            $added = MailWebhookEvent::query()->insertOrIgnore([
                'cuid' => $cuid,
                'provider_event_id' => $eventId,
                'type' => $type,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($added === 1 && $event !== null) {
                Bus::dispatch(new HandleMailWebhookEvent($event));
            }
        });
    }

    final public function handle(MailEvent $event): void
    {
        $delivery = MailDelivery::query()->where('provider_email_id', $event->providerEmailId)->first();
        $context = ['type' => $event->type, 'event' => $event->eventCuid, 'delivery' => $delivery?->cuid];

        if ($delivery !== null) {
            if ($delivery->kind === KnowiiMailKind::Invitation) {
                $this->onInvitation($event, $delivery, $context);

                return;
            }
            $user = $delivery->kind === KnowiiMailKind::Operator || $delivery->user_id === null ? null : User::query()->find($delivery->user_id);
            if ($user === null) {
                Log::info('A report of the mail provider changed nothing: the mail is for no account', $context);

                return;
            }
            $this->onAccount($event, $user, $context);

            return;
        }

        // Without a row, only a mail of this instance counts: the webhook belongs to the whole team at the provider, and other
        // products of the team can send to the same address
        if (! $event->fromThisInstance) {
            Log::info('A report of the mail provider changed nothing: the mail has no row and another sender', $context);

            return;
        }
        $user = User::query()->whereRaw('lower(email) = ?', [$event->address])->first();
        if ($user === null) {
            return;
        }
        if (CommunityInvitation::query()->whereRaw('lower(email) = ?', [$event->address])->exists()) {
            Log::info('A report of the mail provider changed nothing: an invitation holds the same address as an account', $context + ['user' => $user->cuid]);

            return;
        }
        $this->onAccount($event, $user, $context);
    }

    /**
     * @param  array<string, string|null>  $context
     */
    private function onInvitation(MailEvent $event, MailDelivery $delivery, array $context): void
    {
        if ($event->what === MailEvent::DELIVERED) {
            return;
        }
        $invitation = $delivery->community_invitation_id === null
            ? null
            : CommunityInvitation::query()->with('community')->find($delivery->community_invitation_id);
        if ($invitation === null) {
            Log::info('A report of the mail provider changed nothing: the invitation is gone', $context);

            return;
        }

        $type = $event->what === MailEvent::SOFT_BOUNCE ? KnowiiInvitationBounceType::Soft : KnowiiInvitationBounceType::Hard;
        $query = CommunityInvitation::query()->whereKey($invitation->id);
        if ($type === KnowiiInvitationBounceType::Soft) {
            // A soft bounce never hides a hard one
            $query->where(fn ($state) => $state->whereNull('bounce_type')->orWhere('bounce_type', '!=', KnowiiInvitationBounceType::Hard->value));
        }
        $query->update(['bounced_at' => $event->occurredAt, 'bounce_type' => $type->value]);

        Log::info('A report of the mail provider on an invitation', $context + ['invitation' => $invitation->cuid, 'bounce_type' => $type->value]);
        CommunityMembersChanged::dispatch($invitation->community->cuid);
    }

    /**
     * @param  array<string, string|null>  $context
     */
    private function onAccount(MailEvent $event, User $user, array $context): void
    {
        $context['user'] = $user->cuid;
        if (mb_strtolower($user->email) !== $event->address) {
            Log::info('A report of the mail provider changed nothing: it is about another address than the address of the account', $context);

            return;
        }

        $result = DB::transaction(function () use ($event, $user): string {
            // One report of an account at a time: the row of the account is locked
            User::query()->whereKey($user->id)->lockForUpdate()->value('id');
            $row = EmailSuppression::query()->where('user_id', $user->id)->first();

            if ($event->what === MailEvent::DELIVERED) {
                return $this->onDelivery($event, $row);
            }

            if ($row !== null && $row->email !== $event->address) {
                // A row about an address of before
                $row->delete();
                $row = null;
            }
            $row ??= (new EmailSuppression)->forceFill(['cuid' => (new Cuid2(24))->toString(), 'user_id' => $user->id, 'email' => $event->address, 'soft_bounce_count' => 0]);

            return $event->suppresses() ? $this->onSuppression($event, $row) : $this->onSoftBounce($event, $row);
        });

        Log::info('A report of the mail provider on an account: '.$result, $context);
    }

    private function onSuppression(MailEvent $event, EmailSuppression $row): string
    {
        $reason = match ($event->what) {
            MailEvent::COMPLAINT => KnowiiEmailSuppressionReason::Complaint,
            MailEvent::PROVIDER_LIST => KnowiiEmailSuppressionReason::ProviderList,
            default => KnowiiEmailSuppressionReason::HardBounce,
        };

        if ($row->suppressed_at !== null) {
            // A complaint is lifted only by the member: it replaces another reason. Anything else changes nothing
            if ($reason !== KnowiiEmailSuppressionReason::Complaint || $row->reason === KnowiiEmailSuppressionReason::Complaint) {
                return 'already suppressed';
            }
            $row->forceFill(['reason' => $reason, 'last_event_at' => self::later($row->last_event_at, $event->occurredAt)])->save();

            return 'suppressed: '.$reason->value;
        }

        $row->forceFill([
            'reason' => $reason,
            'suppressed_at' => now(),
            'soft_bounce_count' => 0,
            'last_event_at' => self::later($row->last_event_at, $event->occurredAt),
        ])->save();

        return 'suppressed: '.$reason->value;
    }

    private function onSoftBounce(MailEvent $event, EmailSuppression $row): string
    {
        if ($row->suppressed_at !== null) {
            return 'already suppressed';
        }

        $count = $row->soft_bounce_count + 1;
        $values = ['soft_bounce_count' => $count, 'last_event_at' => self::later($row->last_event_at, $event->occurredAt)];
        if ($count >= Constants::EMAIL_SOFT_BOUNCES_TO_SUPPRESS) {
            $values += ['reason' => KnowiiEmailSuppressionReason::SoftBounces, 'suppressed_at' => now()];
        }
        $row->forceFill($values)->save();

        return $count >= Constants::EMAIL_SOFT_BOUNCES_TO_SUPPRESS ? 'suppressed: '.KnowiiEmailSuppressionReason::SoftBounces->value : 'soft bounce '.$count;
    }

    private function onDelivery(MailEvent $event, ?EmailSuppression $row): string
    {
        if ($row === null || $row->email !== $event->address) {
            return 'delivered';
        }
        if ($row->last_event_at !== null && $event->occurredAt->lessThan($row->last_event_at)) {
            return 'delivered before the last report: no change';
        }

        if ($row->suppressed_at !== null) {
            if ($row->reason?->isLiftedByADelivery() !== true) {
                return 'delivered: a complaint stays';
            }
            $row->delete();

            return 'delivered: the suppression is lifted';
        }

        $row->forceFill(['soft_bounce_count' => 0, 'last_event_at' => $event->occurredAt])->save();

        return 'delivered: the count of soft bounces is 0';
    }

    /**
     * The event that the job reads, or null for an event that the platform ignores
     *
     * @param  array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|null>|null>|null>|null>|null>  $data
     */
    private static function eventOf(array $data, string $type, string $cuid): ?MailEvent
    {
        $payload = $data['data'] ?? null;
        if (! is_array($payload)) {
            return null;
        }

        $what = match ($type) {
            'email.bounced' => self::bounceOf($payload['bounce'] ?? null),
            'email.complained' => MailEvent::COMPLAINT,
            'email.suppressed' => MailEvent::PROVIDER_LIST,
            'email.delivered' => MailEvent::DELIVERED,
            default => null,
        };
        $emailId = $payload['email_id'] ?? null;
        $to = $payload['to'] ?? null;
        if ($what === null || ! is_string($emailId) || $emailId === '' || mb_strlen($emailId) > self::MAX_LENGTH_EMAIL_ID
            || ! is_array($to) || ! array_is_list($to) || count($to) !== 1) {
            return null;
        }
        $address = self::addressOf($to[0]);
        if ($address === null) {
            return null;
        }
        $from = self::addressOf($payload['from'] ?? null);
        $ownFrom = config('mail.from.address');

        return new MailEvent(
            $what,
            $type,
            $cuid,
            $emailId,
            $address,
            self::timeOf($data['created_at'] ?? null),
            $from !== null && is_string($ownFrom) && $from === mb_strtolower(trim($ownFrom)),
        );
    }

    /**
     * The address of an item of the payload, with or without a display name ("Ada <ada@example.com>"), in lower case, or
     * null when it is not an address
     *
     * @param  array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|null>|null>|null>|scalar|null  $value
     */
    private static function addressOf(array|string|int|float|bool|null $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $address = Address::create(trim($value))->getAddress();
        } catch (Throwable) {
            return null;
        }

        return $address === '' ? null : mb_strtolower($address);
    }

    /**
     * @param  array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|null>|null>|null>|scalar|null  $bounce
     */
    private static function bounceOf(array|string|int|float|bool|null $bounce): string
    {
        $value = is_array($bounce) ? ($bounce['type'] ?? null) : null;
        if ($value === 'Permanent') {
            return MailEvent::HARD_BOUNCE;
        }
        // The documented soft types of the provider are `Transient` and `Undetermined` (`Temporary` in an example)
        if (! in_array($value, self::SOFT_BOUNCE_TYPES, true)) {
            Log::info('An unknown bounce type of the mail provider counts as a soft bounce', ['bounce_type' => is_string($value) ? mb_substr($value, 0, 32) : get_debug_type($value)]);
        }

        return MailEvent::SOFT_BOUNCE;
    }

    /**
     * @param  array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|null>|null>|null>|null>|scalar|null  $value
     */
    private static function timeOf(array|string|int|float|bool|null $value): CarbonImmutable
    {
        if (is_string($value) && $value !== '') {
            try {
                return CarbonImmutable::parse($value)->utc();
            } catch (Throwable) {
                // A time that cannot be read: the time of the receipt
            }
        }

        return CarbonImmutable::now()->utc();
    }

    private static function later(?\DateTimeInterface $stored, CarbonImmutable $event): CarbonImmutable
    {
        return $stored !== null && $event->lessThan($stored) ? CarbonImmutable::instance($stored) : $event;
    }
}
