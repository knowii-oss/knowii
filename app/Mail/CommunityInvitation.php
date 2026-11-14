<?php

namespace App\Mail;

use App\Models\CommunityInvitation as CommunityInvitationModel;
use App\Models\User;
use App\Support\Retention;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * The email of an invitation to a community (#1242), with a signed link valid for CommunityInvitation::VALIDITY_DAYS.
 *
 * The person did not give us their address (GDPR art. 14): the footer says who invited them, who sends the email,
 * where the address comes from, when it is deleted, and where their rights are (#1345). The inviter is not stored on
 * the invitation: the email only shows their name.
 */
class CommunityInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CommunityInvitationModel $invitation, public User $inviter) {}

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
            'privacyPolicyUrl' => route('privacy-policy.show'),
        ])->subject(__('You are invited to :community on Knowii', ['community' => $this->invitation->community->name]));
    }
}
