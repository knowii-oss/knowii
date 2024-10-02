<?php

namespace App\Actions\Communities;

use App\Constants;
use App\Contracts\Communities\CreatesCommunities;
use App\Enums\KnowiiCommunityVisibility;
use App\Events\Communities\AddingCommunity;
use App\Events\Communities\CommunityCreated;
use App\Exceptions\TechnicalException;
use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateCommunity implements CreatesCommunities
{
  /**
   * Validate and create a new community for the given user.
   *
   * @param User $user
   * @param array $input
   * @return Community
   * @throws TechnicalException
   * @throws AuthorizationException
   * @throws ValidationException
   */
  final public function create(User $user, array $input): Community
  {
    Log::info('Processing request to create a new community');

    Log::debug('Verifying authorizations');
    Gate::forUser($user)->authorize('create', new Community());
    Log::debug('Authorizations verified');

    Log::debug('Validating the input');

    Validator::make($input, [
      // WARNING: Those validation rules must match those in the community creation form in Dashboard.tsx and those in UpdateCommunityName.php
      // The fields MUST also match the ones listed in CommunityApiController
      'name' => ['required', 'string', 'min:'.Constants::$MIN_LENGTH_COMMUNITY_NAME, 'max:'.Constants::$MAX_LENGTH_COMMUNITY_NAME, 'regex:'.Constants::$ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX],
      // Nullable allows empty strings to be passed in
      // Note that the CommunityResource transforms null to an empty string
      // Reference: https://laravel.com/docs/11.x/validation#a-note-on-optional-fields
      'description' => ['nullable', 'string', 'max:' . Constants::$MAX_LENGTH_COMMUNITY_DESCRIPTION],
      'visibility' => ['required', Rule::enum(KnowiiCommunityVisibility::class)],
    ])->validate();

    Log::debug('Input validated');

    AddingCommunity::dispatch($user);

    Log::debug('Saving the new community');

    $communityName = $input['name'];

    try {
      $retVal = $user->ownedCommunities()->create([
        'name' => $communityName,
        // Slug is generated automatically
        'description' => $input['description'],
        'visibility' => $input['visibility'],
      ]);

      Log::info('New community created successfully', ['community' => $retVal]);

      // FIXME probably not needed because the model itself should dispatch events
      // If verified, then remove this, and similar code in other actions
      CommunityCreated::dispatch($retVal);

      return $retVal;
    } catch (\Exception $e) {
      Log::warning('Failed to create the community', ['exception' => $e]);
      throw new TechnicalException('Failed to create the community', null, $e);
    }
  }
}
