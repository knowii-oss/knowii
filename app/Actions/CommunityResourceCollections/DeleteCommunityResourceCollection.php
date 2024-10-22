<?php

namespace App\Actions\CommunityResourceCollections;

use App\Contracts\Communities\DeletesCommunities;
use App\Exceptions\TechnicalException;
use App\Models\Community;
use App\Models\CommunityResourceCollection;
use App\Models\User;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DeleteCommunityResourceCollection implements DeletesCommunities
{
  /**
   * Delete the given resource collection.
   * @throws TechnicalException
   * @throws ValidationException
   * @throws AuthorizationException
   */
  final public function delete(User $user, CommunityResourceCollection $resourceCollection): void
  {
    Log::info("Deleting resource collection", [$resourceCollection->cuid]);
    app(ValidateCommunityResourceCollectionDeletion::class)->validate($user, $resourceCollection);
    try {
      $resourceCollection->purge();
    } catch(Exception $e) {
      Log::warning('Failed to delete the resource collection', ['exception' => $e]);
      throw new TechnicalException('Failed to delete the resource collection', null, $e);
    }

    Log::info("Resource collection deleted", ['resourceCollectionCuid' => $resourceCollection->cuid]);
  }
}
