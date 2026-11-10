<?php

namespace App\Http\Controllers\API;

use App\Contracts\Users\BlocksMembers;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Models\User;
use App\Traits\ApiResponses;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The members that the current user blocked (#1186, ADR 0028), in every community. A blocked member is not told.
 */
class UserBlockApiController extends Controller
{
    use ApiResponses;

    /**
     * The members you blocked, by name.
     *
     * @throws AuthenticationException
     */
    final public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return self::success('Blocked members', null, $user->blockedUsers()->orderBy('name')->get()->toResourceCollection(AuthorResource::class));
    }

    /**
     * Block a member, by user cuid: neither of you starts or continues a one-to-one conversation with the other, and
     * neither adds the other to a group conversation. Blocking twice changes nothing.
     *
     * @throws AuthenticationException
     */
    final public function store(Request $request, string $userCuid): JsonResponse
    {
        app(BlocksMembers::class)->block($request->user() ?? throw new AuthenticationException, User::where('cuid', $userCuid)->firstOrFail());

        return self::success('Member blocked');
    }

    /**
     * Unblock a member, by user cuid.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, string $userCuid): Response
    {
        app(BlocksMembers::class)->unblock($request->user() ?? throw new AuthenticationException, User::where('cuid', $userCuid)->firstOrFail());

        return self::deleted();
    }
}
