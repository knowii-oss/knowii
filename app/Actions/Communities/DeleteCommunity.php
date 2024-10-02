<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\DeletesCommunities;
use App\Exceptions\TechnicalException;
use App\Models\Community;
use App\Models\User;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DeleteCommunity implements DeletesCommunities
{
  /**
   * Delete the given community.
   * @throws TechnicalException
   * @throws ValidationException
   * @throws AuthorizationException
   */
  final public function delete(User $user, Community $community): void
  {
    Log::info("Deleting community", ['communityCuid' => $community->cuid]);
    app(ValidateCommunityDeletion::class)->validate($user, $community);
    try {
      $community->purge();
    } catch(Exception $e) {
      Log::warning('Failed to delete the community', ['exception' => $e]);
      throw new TechnicalException('Failed to create the community', null, $e);
    }

    Log::info("Community deleted", ['communityCuid' => $community->cuid]);
  }
}
