<?php

namespace App\Mail;

use App\Models\Community;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the new owner of a community that they own it now (#1339, ADR 0035).
 */
class CommunityOwnershipTransferred extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Community $community, public User $formerOwner) {}

    /**
     * @return $this
     */
    public function build(): static
    {
        return $this->markdown('emails.community-ownership-transferred', [
            'communityName' => $this->community->name,
            'formerOwnerName' => $this->formerOwner->name,
            'settingsUrl' => route('communities.settings', ['communitySlug' => $this->community->slug]),
        ])->subject(__('You are now the owner of :community on Knowii', ['community' => $this->community->name]));
    }
}
