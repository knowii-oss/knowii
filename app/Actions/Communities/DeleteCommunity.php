<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\DeletesCommunities;
use App\Exceptions\TechnicalException;
use App\Models\Community;
use App\Models\User;
use App\Support\ContentFiles;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeleteCommunity implements DeletesCommunities
{
    public const string NAME_MESSAGE = 'Give the name of the community to delete it';

    /**
     * Delete the community on the request of its owner (the API and the screen): the request names the community that
     * it deletes, so that a request made for another community, or by mistake, deletes nothing (ADR 0035 point 4).
     *
     * @throws TechnicalException
     * @throws ValidationException
     * @throws AuthorizationException
     */
    final public function deleteWithName(User $user, Community $community, array $input): void
    {
        // Who may delete comes first: a person who may not gets the same refusal with any name
        app(ValidateCommunityDeletion::class)->validate($user, $community);

        /** @var array{name: string} $validated */
        $validated = Validator::make($input, ['name' => ['required', 'string']])->validate();

        // The check of the screen, done again here: the name of the community, whatever the case
        if (mb_strtolower(trim($validated['name'])) !== mb_strtolower(trim($community->name))) {
            throw ValidationException::withMessages(['name' => self::NAME_MESSAGE]);
        }

        $this->delete($user, $community);
    }

    /**
     * Delete the given community.
     *
     * A deadlock or a serialization failure goes out as it is, so that the transaction of the caller can run again (the
     * deletion of an account, DeleteUser). Any other error of the deletion becomes a TechnicalException.
     *
     * @throws TechnicalException
     * @throws ValidationException
     * @throws AuthorizationException
     */
    final public function delete(User $user, Community $community): void
    {
        Log::info('Deleting community', [$community->cuid]);
        app(ValidateCommunityDeletion::class)->validate($user, $community);
        // Read before the rows go: the files are deleted after (#1265)
        $files = ContentFiles::ofCommunity($community);
        try {
            $community->purge();
        } catch (Exception $e) {
            if (app(ConcurrencyErrorDetector::class)->causedByConcurrencyError($e)) {
                throw $e;
            }
            Log::warning('Failed to delete the community', ['exception' => $e]);
            throw new TechnicalException('Failed to delete the community', null, $e);
        }

        $files->deleteAfterCommit();

        Log::info('Community deleted', ['communityCuid' => $community->cuid]);
    }
}
