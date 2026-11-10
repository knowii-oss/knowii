<?php

namespace App\Http\Controllers\API;

use App\Contracts\Communities\CreatesCommunities;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\Communities\UpdatesCommunities;
use App\Enums\KnowiiCommunityVisibility;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Support\Pagination;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

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
     * @throws AuthenticationException
     */
    /**
     * Get a community with its description, its moderator rights and its number of members (#1135). Members only.
     */
    final public function show(Community $community): JsonResponse
    {
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

    final public function destroy(Request $request, Community $community): Response
    {
        Log::info('Processing API request to delete a community.');
        Log::debug('User: ', [$request->user()]);

        $deleter = app(DeletesCommunities::class);
        $deleter->delete($request->user() ?? throw new AuthenticationException('User must be authenticated'), $community);

        return self::deleted();
    }
}
