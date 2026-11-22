<?php

namespace App\Events\InvitationImports;

use App\Constants;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An import of invitations moved on (#1390, ADR 0061): it was sent, its rows ended by 25, or it ended. Identifiers only:
 * the page loads the import through the API, which checks the access. Sent to the private channel of the person who
 * uploaded it (`user.{cuid}`): the other managers see the new state when they load the page.
 */
class InvitationImportChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public const string NAME = 'invitation_imports.changed';

    public function __construct(
        public readonly string $userCuid,
        public readonly string $communityCuid,
        public readonly string $importCuid,
    ) {}

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['community_cuid' => $this->communityCuid, 'import_cuid' => $this->importCuid];
    }

    public function broadcastAs(): string
    {
        return self::NAME;
    }

    public function broadcastQueue(): string
    {
        return Constants::QUEUE_BROADCASTS;
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(str_replace(Constants::WS_CHANNEL_USER_PARAM_USER_CUID, $this->userCuid, Constants::WS_CHANNEL_USER))];
    }
}
