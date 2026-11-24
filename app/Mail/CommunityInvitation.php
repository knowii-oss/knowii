<?php

namespace App\Mail;

use App\Jobs\Middleware\HideMailErrorText;
use App\Mail\Concerns\HasDeliveryRow;
use App\Models\CommunityInvitation as CommunityInvitationModel;
use App\Models\User;
use App\Support\LegalPages;
use App\Support\MailText;
use App\Support\Retention;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * The email of an invitation to a community (#1242), with a signed link valid for CommunityInvitation::VALIDITY_DAYS.
 *
 * The person did not give us their address (GDPR art. 14): the footer says who invited them, who sends the email,
 * where the address comes from, when it is deleted, and where their rights are (#1345). The inviter is not stored on
 * the invitation: the email only shows their name. The sentence about the rights names the privacy policy: it shows only
 * when the instance has that text (#1653).
 *
 * It holds the address of the invited person, so it is encrypted in the queue (ADR 0064). A mail that fails keeps no
 * recipient address (HideMailErrorText).
 */
class CommunityInvitation extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use HasDeliveryRow, Queueable, SerializesModels;

    public function __construct(public CommunityInvitationModel $invitation, public User $inviter) {}

    /**
     * A mail that fails keeps no recipient address: neither the log nor `failed_jobs` gets the text of the error
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new HideMailErrorText];
    }

    /**
     * @return $this
     */
    public function build(): static
    {
        return $this->markdown('emails.community-invitation', [
            'acceptUrl' => URL::temporarySignedRoute('community-invitations.accept', $this->invitation->expiresAt(), [
                'invitation' => $this->invitation,
            ]),
            'communityName' => $this->invitation->community->name,
            'expiresAt' => $this->invitation->expiresAt(),
            'inviterName' => $this->inviter->name,
            'operator' => config('legal.operator'),
            'deletedAfter' => Retention::periods()['expired_invitations'],
            // Null when the instance has no privacy policy (#1653): the email then leaves the sentence out
            'privacyPolicyUrl' => LegalPages::exists('privacy-policy') ? route('privacy-policy.show') : null,
        ])->subject(__('You are invited to :community on Knowii', ['community' => MailText::subject($this->invitation->community->name)]));
    }
}
