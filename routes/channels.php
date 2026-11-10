<?php

use App\Broadcasting\CommunityOnlineChannel;
use App\Broadcasting\ConversationPresenceChannel;
use App\Broadcasting\SpacePresenceChannel;
use App\Constants;
use App\Models\Community;
use App\Models\Conversation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('App.Models.User.{id}', static function ($user, $id) {
//    return (int) $user->id === (int) $id;
// });

Broadcast::channel(Constants::WS_CHANNEL_COMMUNITIES, static function (User $user) {
    // This is a public channel sharing events about public communities
    return true;
});

Broadcast::channel(Constants::WS_CHANNEL_COMMUNITY, static function (User $user, string $communityCuid) {
    // This is a private channel reserved to community members
    $community = Community::whereCuid($communityCuid)->firstOrFail();
    $retVal = $user->allCommunities()->contains($community);

    return $retVal;
});

Broadcast::channel(Constants::WS_CHANNEL_COMMUNITY_SPACE, static function (User $user, string $communityCuid, string $spaceCuid) {
    // Private channel of a space: same rule as reading the space (SpacePolicy::view)
    $community = Community::whereCuid($communityCuid)->firstOrFail();
    $space = $community->spaces()->where('cuid', $spaceCuid)->firstOrFail();

    return $user->can('view', $space);
});

// Presence channels (#1187, ADR 0027): members who hide their online status never join
Broadcast::channel(Constants::WS_CHANNEL_COMMUNITY_ONLINE, CommunityOnlineChannel::class);
Broadcast::channel(Constants::WS_CHANNEL_COMMUNITY_SPACE_PRESENCE, SpacePresenceChannel::class);

// Direct messages (#1186, ADR 0028): the participants who are still members of the community
Broadcast::channel(Constants::WS_CHANNEL_CONVERSATION, static function (User $user, string $conversationCuid) {
    $conversation = Conversation::whereCuid($conversationCuid)->first();

    return $conversation !== null && $user->can('view', $conversation);
});
Broadcast::channel(Constants::WS_CHANNEL_CONVERSATION_PRESENCE, ConversationPresenceChannel::class);

// The events of one member: their conversations changed (#1186)
Broadcast::channel(Constants::WS_CHANNEL_USER, static function (User $user, string $userCuid) {
    return $user->cuid === $userCuid;
});
