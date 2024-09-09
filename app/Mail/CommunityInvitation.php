<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

use App\CommunityInvitation as CommunityInvitationModel;

class CommunityInvitation extends Mailable
{
  use Queueable, SerializesModels;

  /**
   * The invitation instance.
   *
   * @var \App\CommunityInvitation
   */
  public $invitation;

  /**
   * Create a new message instance.
   *
   * @param \App\CommunityInvitation $invitation
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
