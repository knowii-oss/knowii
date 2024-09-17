<?php

namespace App\Actions\Communities;

use App\Constants;
use App\Contracts\Communities\UpdatesCommunityNames;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateCommunityName implements UpdatesCommunityNames
{
  /**
   * Validate and update the given community's name.
   *
   * @param User $user
   * @param Community $community
   * @param array $input
   */
  public function update(User $user, Community $community, array $input): void
  {
    Gate::forUser($user)->authorize('update', $community);

    Validator::make($input, [
      // WARNING: Those validation rules must match those in the community creation form in Dashboard.tsx and those in CreateCommunity.php
      'name' => ['required', 'string', 'min:'.Constants::$MIN_LENGTH_COMMUNITY_NAME, 'max:'.Constants::$MAX_LENGTH_COMMUNITY_NAME, 'regex:'.Constants::$ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX],
    ]);

    $community->forceFill([
      'name' => $input['name'],
    ])->save();
  }
}
