<?php

namespace App\Mail;

use App\Jobs\Middleware\HideMailErrorText;
use App\Mail\Concerns\HasDeliveryRow;
use App\Models\Community;
use App\Models\User;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the new owner of a community that they own it now (#1339, ADR 0035). It holds the address of the new owner, so it
 * is encrypted in the queue (ADR 0064). A mail that fails keeps no recipient address (HideMailErrorText).
 */
class CommunityOwnershipTransferred extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use HasDeliveryRow, Queueable, SerializesModels;

    public function __construct(public Community $community, public User $formerOwner) {}

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
        return $this->markdown('emails.community-ownership-transferred', [
            'communityName' => $this->community->name,
            'formerOwnerName' => $this->formerOwner->name,
            'settingsUrl' => route('communities.settings', ['communitySlug' => $this->community->slug]),
        ])->subject(__('You are now the owner of :community on Knowii', ['community' => MailText::subject($this->community->name)]));
    }
}
