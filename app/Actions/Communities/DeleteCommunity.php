<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\DeletesCommunities;
use App\Knowii;
use App\Models\User;

class DeleteCommunity implements DeletesCommunities
{

  /**
   * Delete the given community.
   */
  final public function delete(User $user, string $communityCuid): void
  {
    $community = Knowii::newCommunityModel()->whereCuid($communityCuid)->firstOrFail();
    app(ValidateCommunityDeletion::class)->validate($user, $community);

    $community->purge();
  }
}
