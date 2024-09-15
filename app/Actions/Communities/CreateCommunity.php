<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\CreatesCommunities;
use App\Events\Communities\AddingCommunity;
use App\Exceptions\TechnicalException;
use App\KnowiiCommunityVisibility;
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
   * @throws TechnicalException
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
      'visibility' => ['required', 'string', 'in:' . KnowiiCommunityVisibility::toCommaSeparatedString()],
    ])->validate();

    Log::debug('Input validated');

    AddingCommunity::dispatch($user);

    Log::debug('Saving the new community');

    // At this point business validations are done, so all that can happen is a technical issue

    try {
      $retVal = $user->ownedCommunities()->create([
        'name' => $input['name'],
        'description' => $input['description'],
        'visibility' => $input['visibility'],
      ]);
      Log::info('New community created successfully', ['community' => $retVal]);

      return $retVal;
    } catch (\Exception $e) {
      Log::warning('Failed to create the community', ['exception' => $e]);
      throw new TechnicalException('Failed to create the community', null, $e);
    }
  }
}
