<?php

namespace App\Actions\CommunityResourceCollections;

use App\Constants;
use App\Contracts\CommunityResourceCollections\CreatesCommunityResourceCollections;
use App\Exceptions\TechnicalException;
use App\Models\Community;
use App\Models\CommunityResourceCollection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateCommunityResourceCollection implements CreatesCommunityResourceCollections
{
    /**
     * Validate and create a new resource collection for the given community.
     *
     * @throws TechnicalException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    final public function create(User $user, Community $community, array $input): CommunityResourceCollection
    {
        Log::info('Processing request to create a new community resource collection');

        Log::debug('Verifying authorizations');
        Gate::forUser($user)->authorize('createResourceCollection', $community);
        Log::debug('Authorizations verified');

        Log::debug('Validating the input');
        $validator = Validator::make($input, [
            // WARNING: The fields MUST also match the ones listed in CommunityResourceCollectionApiController
            'name' => ['required', 'string', 'min:'.Constants::$MIN_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME, 'max:'.Constants::$MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME, 'regex:'.Constants::$ALLOWED_COMMUNITY_RESOURCE_COLLECTION_NAME_CHARACTERS_REGEX],
            // Nullable allows empty strings to be passed in
            // Note that the CommunityResource transforms null to an empty string
            // Reference: https://laravel.com/docs/11.x/validation#a-note-on-optional-fields
            'description' => ['nullable', 'string', 'max:'.Constants::$MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_DESCRIPTION],
        ]);

        $validator->validate();

        /** @var array{name: string, description: string|null} $input */
        $input = $validator->validated();
        Log::debug('Input validated');

        Log::debug('Saving the new community resource collection');

        try {
            $collection = $community->communityResourceCollections()->create([
                'name' => $input['name'],
                'description' => $input['description'],
            ]);

            Log::info('New community resource collection created successfully', ['collection' => $collection]);

            return $collection;
        } catch (\Exception $e) {
            Log::warning('Failed to create the community resource collection', ['exception' => $e]);
            throw new TechnicalException('Failed to create the community resource collection', null, $e);
        }
    }
}
