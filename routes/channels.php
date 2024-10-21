<?php

use App\Constants;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

//Broadcast::channel('App.Models.User.{id}', static function ($user, $id) {
//    return (int) $user->id === (int) $id;
//});

Broadcast::channel(Constants::$WS_CHANNEL_COMMUNITIES, static function (User $user) {
  // This is a public channel sharing events about public communities
  return true;
});

Broadcast::channel(Constants::$WS_CHANNEL_COMMUNITY, static function (User $user, string $communityCuid) {
  // This is a private channel reserved to community members
  $community = Community::whereCuid($communityCuid)->firstOrFail();
  $retVal = $user->allCommunities()->contains($community);

  return $retVal;
});
