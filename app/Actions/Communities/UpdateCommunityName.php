<?php

namespace App\Actions\Communities;

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
            'name' => ['required', 'string', 'max:255'],
        ]);

        $community->forceFill([
            'name' => $input['name'],
        ])->save();
    }
}
