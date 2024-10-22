<?php

namespace App\Actions\CommunityResourceCollections;

use App\Models\CommunityResourceCollection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ValidateCommunityResourceCollectionDeletion
{
  /**
   * Validate that the resource collection can be deleted by the given user.
   *
   * @param User $user
   * @param CommunityResourceCollection $resourceCollection
   * @return void
   * @throws AuthorizationException
   */
  public function validate(User $user, CommunityResourceCollection $resourceCollection): void
  {
    Log::info("Verifying resource collection deletion authorization", ['resourceCollection' => $resourceCollection, 'community' => $resourceCollection->community, 'user' => $user]);
    Gate::forUser($user)->authorize('deleteResourceCollection', $resourceCollection->community);
    Log::info("Resource collection deletion authorized", ['resourceCollection' => $resourceCollection, 'community' => $resourceCollection->community, 'user' => $user]);
  }
}
