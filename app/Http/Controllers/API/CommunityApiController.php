<?php

namespace App\Http\Controllers\API;

use App\Contracts\Communities\CreatesCommunities;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\Communities\TransfersCommunityOwnership;
use App\Contracts\Communities\UpdatesCommunities;
use App\Contracts\Communities\VerifiesCommunityNameAvailability;
use App\Enums\KnowiiCommunityVisibility;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Support\Pagination;
use App\Support\PublicCommunity;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CommunityApiController extends Controller
{
    use ApiResponses;

    /**
     * List the communities of the current member: the ones they own and the ones they belong to, by name (#828).
     * Offset pagination (ADR 0011). Lists never carry the large fields (moderator rights, member count): get one community.
     *
     * @throws AuthenticationException
     */
    #[QueryParameter('limit', description: 'Number of communities (default 10, maximum 100)', type: 'int', example: 10)]
    #[QueryParameter('offset', description: 'Number of communities to skip (default 0)', type: 'int', example: 0)]
    #[QueryParameter('visibility', description: 'Only this visibility: personal, private or public', type: 'string', example: 'public')]
    #[QueryParameter('query', description: 'Only the communities whose name contains this text (case-insensitive)', type: 'string', example: 'knowledge')]
    final public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        $visibility = KnowiiCommunityVisibility::tryFrom((string) $request->query('visibility', ''));
        $search = trim((string) $request->query('query', ''));

        $communities = Community::query()
            ->where(static fn ($query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('users', static fn ($members) => $members->whereKey($user->id)))
            ->when($visibility !== null, static fn ($query) => $query->where('visibility', $visibility))
            ->when($search !== '', static fn ($query) => $query->whereRaw('name ILIKE ?', ['%'.addcslashes($search, '%_\\').'%']))
            ->orderBy('name')
            ->orderBy('id');

        $page = Pagination::fromRequest($request)->apply($communities);

        return self::success('Communities', $page['metadata'], $page['items']->toResourceCollection());
    }

    /**
     * Check whether a shared (private or public) community can take a name (#1136, ADR 0033): no other shared community
     * has this name, whatever the case, or the address that it gives. The answer never tells which community took it.
     * To check a new name for a community that you own, send its cuid in `community`: its own name stays available.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name to check (3 to 64 characters: letters, digits, spaces, dashes, apostrophes, @)', required: true, type: 'string', example: 'Knowledge Builders')]
    #[BodyParameter('community', description: 'cuid of the community that you rename (its owner only)', required: false, type: 'string')]
    final public function isNameAvailable(Request $request): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        $available = app(VerifiesCommunityNameAvailability::class)->verify($user, $request->only(['name', 'community']));

        return self::success('Community name availability checked', null, ['available' => $available]);
    }

    /**
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the community (3 to 64 characters: letters, digits, spaces, dashes, apostrophes, @)', required: true, type: 'string', example: 'Knowledge Builders')]
    #[BodyParameter('description', description: 'Short description (up to 255 characters)', required: false, type: 'string')]
    #[BodyParameter('visibility', description: 'personal, private or public', required: true, type: 'string', example: 'public')]
    final public function store(Request $request): JsonResponse
    {
        Log::info('Processing API request to create a new community.');
        Log::debug('User: ', [$request->user()]);

        // Filter the input to only include the fields that are needed/accepted
        $input = $request->only(['name', 'description', 'visibility']);
        Log::debug('Input', [$input]);

        $creator = app(CreatesCommunities::class);
        $createdItem = $creator->create($request->user() ?? throw new AuthenticationException('User must be authenticated'), $input);

        return self::created($createdItem->toResource()->withLargeFields(), 'Community created successfully');
    }

    /**
     * Get a community. A member gets it with its description, its moderator rights and its number of members (#1135).
     * A visitor and a signed-in person who is not a member get the public shape (cuid, name, slug, description,
     * visibility, `is_member` false) when the community has at least one public space that is not a chat space (#1485,
     * ADR 0044). Otherwise a visitor gets 404 (like for a community that does not exist) and a signed-in person who is
     * not a member gets 403.
     */
    final public function show(Request $request, Community $community): JsonResponse
    {
        if (! PublicCommunity::isMember($request->user(), $community)) {
            PublicCommunity::ensureExists($community, $request);

            return self::success('Community', null, $community->toResource());
        }

        Gate::authorize('view', $community);

        return self::success('Community', null, $community->loadCount('users')->toResource()->withLargeFields());
    }

    /**
     * Change the name, the description or the visibility of a community (owner only, #1129). The slug stays the same.
     * The visibility changes between private and public only.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the community', required: false, type: 'string', example: 'Knowii Community')]
    #[BodyParameter('description', description: 'Description of the community (can be empty)', required: false, type: 'string')]
    #[BodyParameter('visibility', description: 'private or public (a personal community stays personal)', required: false, type: 'string', example: 'private')]
    #[BodyParameter('direct_messages_enabled', description: 'false: nobody starts a conversation or sends a direct message; the members still read theirs (#1186)', required: false, type: 'boolean', example: true)]
    final public function update(Request $request, Community $community): JsonResponse
    {
        $community = app(UpdatesCommunities::class)->update($request->user() ?? throw new AuthenticationException, $community, $request->only(['name', 'description', 'visibility', 'direct_messages_enabled']));

        return self::success('Community updated', null, $community->loadCount('users')->toResource()->withLargeFields());
    }

    /**
     * Transfer the ownership of the community to one of its admins (owner only, #1339, ADR 0035). The new owner gets
     * an email, and the former owner becomes an admin. The owner confirms with their password: after 5 wrong passwords in
     * 15 minutes of the tokens of the account together, each token of the account gets a 429 until the 15 minutes are over
     * (`Retry-After`). A session of the web application ends at its fifth wrong password (401). Never for a personal
     * community. Returns the community.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('member', description: 'The user cuid of the new owner: an admin of the community', required: true, type: 'string', example: 'tz4a98xxat96iws9zmbrgj3a')]
    #[BodyParameter('password', description: 'The password of the current owner', required: true, type: 'string')]
    final public function transferOwnership(Request $request, Community $community): JsonResponse
    {
        app(TransfersCommunityOwnership::class)->transfer($request->user() ?? throw new AuthenticationException, $community, $request->only(['member', 'password']));

        return self::success('Ownership transferred', null, $community->refresh()->loadCount('users')->toResource()->withLargeFields());
    }

    /**
     * Delete the community, with its spaces, the content of every member and the files, at once and for good: the owner
     * only. The body names the community: `name` must be its name (whatever the case), as the screen asks.
     *
     * Needs the ability `communities:delete`, which is never in the default set of a token: `communities:write` does not
     * delete a community. Answers 204.
     *
     * @throws AuthenticationException
     * @throws ValidationException
     */
    #[BodyParameter('name', description: 'The name of the community that you delete (whatever the case)', required: true, type: 'string', example: 'Knowledge Builders')]
    final public function destroy(Request $request, Community $community): Response
    {
        Log::info('Processing API request to delete a community.');
        Log::debug('User: ', [$request->user()]);

        $deleter = app(DeletesCommunities::class);
        $deleter->deleteWithName($request->user() ?? throw new AuthenticationException('User must be authenticated'), $community, $request->only(['name']));

        return self::deleted();
    }
}
