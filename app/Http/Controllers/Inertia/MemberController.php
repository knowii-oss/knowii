<?php

namespace App\Http\Controllers\Inertia;

use App\Constants;
use App\Enums\KnowiiMemberSort;
use App\Enums\KnowiiNoAccessKind;
use App\Http\Resources\MemberProfileResource;
use App\Http\Resources\MemberWithRoleResource;
use App\Models\Community;
use App\Models\User;
use App\Support\CommunityMemberQuery;
use App\Support\MemberTagsOfMembers;
use App\Support\NoAccess;
use App\Support\Pagination;
use App\Support\SpaceTree;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class MemberController extends Controller
{
    /**
     * The request attribute of an answer of the directory that read no member list (a reload for another prop of the
     * page): the limiter `member-lists` does not count such an answer
     */
    public const string NO_MEMBER_LIST_ATTRIBUTE = 'member-lists.none';

    /**
     * The member directory of a community (#148, ADR 0056): its members, for its members, with a search by name and two
     * sorts. The first entries come with the page; the page loads the next ones through the API list of the members. An
     * entry is the one of that list (MemberWithRoleResource): the headline is its only profile field.
     */
    public function index(Request $request, string $communitySlug): Response
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        NoAccess::authorizeView($request, $community, $community, KnowiiNoAccessKind::Community);

        JsonResource::withoutWrapping();

        // Every prop is a closure: a visit builds only the props that it asks for. A search asks for `members`, `sort` and
        // `query`, so the community and its navigation are not built again. A reload for another prop (the community
        // after an event of its channel) reads no member, and the request says so until the list is read
        $request->attributes->set(self::NO_MEMBER_LIST_ATTRIBUTE, true);

        /** @var array{members: array{items: ResourceCollection, pagination: array<string, int|null>}, sort: string, query: string}|null $list */
        $list = null;
        // One value for the three props of the list: the first prop that is asked for reads the members, one time
        $directory = static function () use (&$list, $request, $community): array {
            if ($list !== null) {
                return $list;
            }

            // Any other value of `sort` than "newest" means "name": the page never shows the order of the roles. A value
            // that is not a text (`?query[]=a`, `?sort[]=newest`) counts as no value
            $sort = $request->query('sort');
            $search = $request->query('query');
            $sort = KnowiiMemberSort::forDirectory(is_string($sort) ? $sort : null);
            $search = CommunityMemberQuery::searchText(is_string($search) ? $search : '');
            $page = (new Pagination(Constants::COMMUNITY_MEMBERS_PAGE_SIZE, 0))->apply(CommunityMemberQuery::for($community, $search, $sort));
            // The entries are those of the API list: their tags too, with one query for the page (#1389, ADR 0060)
            MemberTagsOfMembers::attach($community, $page['items'], $request->user());
            $request->attributes->set(self::NO_MEMBER_LIST_ATTRIBUTE, false);

            return $list = [
                'members' => [
                    'items' => $page['items']->toResourceCollection(MemberWithRoleResource::class),
                    'pagination' => $page['metadata']['pagination'],
                ],
                'sort' => $sort->value,
                'query' => $search,
            ];
        };

        // WARNING: aligned with the props of Pages/Members/Index.tsx
        return Jetstream::inertia()->render($request, 'Members/Index', [
            'community' => static fn () => $community->toResource()->withLargeFields(),
            'spaceGroups' => static fn () => SpaceTree::for($community, $request->user())->toResourceCollection(),
            'members' => static fn (): array => $directory()['members'],
            'sort' => static fn (): string => $directory()['sort'],
            'query' => static fn (): string => $directory()['query'],
            // The link "Manage members" to the settings: the people who can add members (#1240)
            'canManageMembers' => static fn (): bool => Gate::check('addCommunityMember', $community),
        ]);
    }

    /**
     * The profile of a member, as the other members of the community see it (mentions link here). The member tags are
     * those that the reader may see (#1389, ADR 0060): the member themself gets what another member gets, and reads the
     * tags of the managers in the export of their data.
     */
    public function show(Request $request, string $communitySlug, string $username): Response
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        NoAccess::authorizeView($request, $community, $community, KnowiiNoAccessKind::Community);

        $member = User::with('profile')->whereRaw('lower(username) = ?', [mb_strtolower($username)])->firstOrFail();
        abort_unless($member->belongsToCommunity($community), 404);

        JsonResource::withoutWrapping();

        // Direct messages (#1186): what the current member can do with this member
        $viewer = $request->user();
        $isSelf = $viewer !== null && $viewer->is($member);
        $blockedByMe = $viewer !== null && ! $isSelf && $viewer->blockedUsers()->whereKey($member->id)->exists();

        // WARNING: aligned with the props of Pages/Members/Show.tsx
        return Jetstream::inertia()->render($request, 'Members/Show', [
            'community' => $community->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $request->user())->toResourceCollection(),
            'member' => $member->toResource(MemberProfileResource::class),
            'memberTags' => (MemberTagsOfMembers::for($community, [$member], $viewer)[$member->id] ?? new Collection)->toResourceCollection(),
            'messaging' => [
                'isSelf' => $isSelf,
                'blockedByMe' => $blockedByMe,
                'canMessage' => $viewer !== null && ! $isSelf && $viewer->belongsToCommunity($community)
                    && $community->allowsDirectMessages() && ! $viewer->hasBlockWith($member),
            ],
        ]);
    }
}
