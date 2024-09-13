<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\CreatesCommunities;
use App\Events\Communities\AddingCommunity;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreateCommunity implements CreatesCommunities
{
  /**
   * Validate and create a new community for the given user.
   *
   * @param User $user
   * @param array $input
   * @return Community
   */
    final public function create(User $user, array $input): Community
    {
        Gate::forUser($user)->authorize('create', new Community());

        Validator::make($input, [
            'name' => ['required', 'string', 'min: 3', 'max:255'],
            'description' => ['required', 'string', 'max:255'], // FIXME extend length
            'personal' => ['required', 'boolean'],
        ])->validateWithBag('createCommunity');

        AddingCommunity::dispatch($user);

        $retVal = $user->ownedCommunities()->create([
          'name' => $input['name'],
          'description' => $input['description'],
          'personal' => $input['personal'],
        ]);

        return $retVal;
    }
}
