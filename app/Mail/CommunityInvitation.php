<?php

namespace App\Mail;

use App\CommunityInvitation as CommunityInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class CommunityInvitation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The invitation instance.
     */
    public CommunityInvitationModel $invitation;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(CommunityInvitationModel $invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.community-invitation', ['acceptUrl' => URL::signedRoute('community-invitations.accept', [
            'invitation' => $this->invitation,
        ])])->subject(__('Community Invitation'));
    }
}
