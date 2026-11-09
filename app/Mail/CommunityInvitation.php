<?php

namespace App\Mail;

use App\Models\CommunityInvitation as CommunityInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * The email of an invitation to a community (#1242), with a signed link valid for CommunityInvitation::VALIDITY_DAYS.
 */
class CommunityInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CommunityInvitationModel $invitation) {}

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
        ])->subject(__('You are invited to :community on Knowii', ['community' => $this->invitation->community->name]));
    }
}
