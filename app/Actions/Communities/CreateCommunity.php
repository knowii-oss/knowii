<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\CreatesCommunities;
use App\Events\Communities\AddingCommunity;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
    Log::info('Processing request to create a new community');

    Log::debug('Verifying authorizations');
    Gate::forUser($user)->authorize('create', new Community());
    Log::debug('Authorizations verified');

    Log::debug('Validating the input');
    Validator::make($input, [
      'name' => ['required', 'string', 'min: 3', 'max:255'],
      // Nullable allows empty strings to be passed in
      // Note that the CommunityResource transforms null to an empty string
      // Reference: https://laravel.com/docs/11.x/validation#a-note-on-optional-fields
      'description' => ['nullable', 'string', 'max:255'], // FIXME extend length
      'personal' => ['required', 'boolean'],
    ]);
    Log::debug('Input validated');

    AddingCommunity::dispatch($user);

    Log::debug('Saving the new community');
    $retVal = $user->ownedCommunities()->create([
      'name' => $input['name'],
      'description' => $input['description'],
      'personal' => $input['personal'],
    ]);
    Log::debug('New community');

    return $retVal;
  }
}
