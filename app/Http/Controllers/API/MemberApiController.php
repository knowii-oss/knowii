<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Models\Community;
use App\Models\User;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Members of a community (#1177: the mention autocomplete). The members directory comes in M3.
 */
class MemberApiController extends Controller
{
    use ApiResponses;

    private const int MAX_RESULTS = 20;

    /**
     * Find members of the community by the beginning of their username or of a word of their name.
     */
    #[QueryParameter('query', description: 'Beginning of the username or of a word of the name (at least 1 character)', type: 'string', example: 'ada')]
    #[QueryParameter('limit', description: 'Number of members (default 8, maximum 20)', type: 'int', example: 8)]
    final public function search(Request $request, Community $community): JsonResponse
    {
        Gate::authorize('view', $community);

        $query = trim((string) $request->query('query', ''));
        $limit = min(max((int) $request->query('limit', '8'), 1), self::MAX_RESULTS);

        if ($query === '') {
            return self::success('Members', null, []);
        }

        // LIKE wildcards in the input are literal
        $prefix = addcslashes(mb_strtolower($query), '%_\\').'%';

        $members = User::query()
            ->where(function ($scope) use ($community) {
                $scope->where('id', $community->owner_id)->orWhereIn('id', $community->users()->select('users.id'));
            })
            ->where(function ($scope) use ($prefix) {
                $scope->whereRaw('lower(username) like ?', [$prefix])
                    ->orWhereRaw('lower(name) like ?', [$prefix])
                    ->orWhereRaw('lower(name) like ?', ['% '.$prefix]);
            })
            ->orderByRaw('lower(username) like ? desc', [$prefix])
            ->orderBy('username')
            ->limit($limit)
            ->get();

        return self::success('Members', null, $members->toResourceCollection(AuthorResource::class));
    }
}
