<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\DeletesCommunities;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DeleteCommunity implements DeletesCommunities
{

  /**
   * Delete the given community.
   */
  final public function delete(User $user, string $communityCuid): void
  {
    Log::info("Deleting community", ['communityCuid' => $communityCuid]);
    $community = (new Community())->whereCuid($communityCuid)->firstOrFail();
    app(ValidateCommunityDeletion::class)->validate($user, $community);
    $community->purge();
    Log::info("Community deleted", ['communityCuid' => $communityCuid]);
  }
}
