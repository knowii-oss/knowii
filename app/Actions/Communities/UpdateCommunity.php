<?php

namespace App\Actions\Communities;

use App\Constants;
use App\Contracts\Communities\UpdatesCommunities;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use App\Rules\AvailableCommunityName;
use App\Support\LiveConnections;
use App\Support\PlainText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateCommunity implements UpdatesCommunities
{
    /**
     * Change the details of a community (#1129): the owner only. The slug never changes, so links keep working (#508).
     * The visibility changes between private and public only: a personal community stays personal, and a community
     * with members never becomes personal.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    final public function update(User $user, Community $community, array $input): Community
    {
        Gate::forUser($user)->authorize('update', $community);

        PlainText::clean($input, ['name'], ['description']);
        // WARNING: the same rules as CreateCommunity.php and newCommunitySchema (community.schema.ts)
        /** @var array{name?: string, description?: string|null, visibility?: string, direct_messages_enabled?: bool} $validated */
        $validated = Validator::make($input, [
            // A unique name for a shared community (ADR 0033, #1136); the reserved names hold for a personal one too
            // (ADR 0036): its address comes from its name
            'name' => [
                'sometimes', 'required', 'string', 'min:'.Constants::MIN_LENGTH_COMMUNITY_NAME, 'max:'.Constants::MAX_LENGTH_COMMUNITY_NAME, 'regex:'.Constants::ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX,
                new AvailableCommunityName($community, $user, $community->visibility !== KnowiiCommunityVisibility::Personal),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_COMMUNITY_DESCRIPTION],
            'visibility' => ['sometimes', 'required', Rule::enum(KnowiiCommunityVisibility::class)],
            // #1186: off, nobody starts a conversation or sends a direct message; the members still read theirs
            'direct_messages_enabled' => ['sometimes', 'boolean'],
        ])->validate();

        if (array_key_exists('visibility', $validated)) {
            $visibility = KnowiiCommunityVisibility::from($validated['visibility']);
            $wasPersonal = $community->visibility === KnowiiCommunityVisibility::Personal;
            if ($visibility !== $community->visibility && ($wasPersonal || $visibility === KnowiiCommunityVisibility::Personal)) {
                throw ValidationException::withMessages(['visibility' => 'A personal community stays personal, and a shared community cannot become personal']);
            }
        }

        $visibilityBefore = $community->visibility;
        $directMessagesBefore = $community->allowsDirectMessages();

        $community->update($validated);

        Log::info('Community updated', ['community' => $community->cuid, 'fields' => array_keys($validated)]);

        // Who reads what, or who types in a conversation, changed: the live connections of the members end (ADR 0075)
        if ($community->visibility !== $visibilityBefore || ($directMessagesBefore && ! $community->allowsDirectMessages())) {
            LiveConnections::endForMembersOf($community);
        }

        return $community;
    }
}
