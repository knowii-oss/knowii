<?php

namespace App\Http\Controllers\API;

use App\Contracts\Communities\UpdatesModeratorPermissions;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The rights of the moderators of a community (#1240, ADR 0017). Changes are delegated to the action (ADR 0006).
 */
class ModeratorPermissionApiController extends Controller
{
    use ApiResponses;

    private const array FIELDS = ['manage_spaces', 'manage_members', 'pin_posts', 'delete_content'];

    /**
     * Switch some rights of the moderators (owner and admins). Returns the community with its moderator rights.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('manage_spaces', description: 'Moderators create, edit, move and delete space groups and spaces', required: false, type: 'bool')]
    #[BodyParameter('manage_members', description: 'Moderators add members, change roles and remove members (role hierarchy applies)', required: false, type: 'bool')]
    #[BodyParameter('pin_posts', description: 'Moderators pin and unpin posts', required: false, type: 'bool')]
    #[BodyParameter('delete_content', description: 'Moderators delete the posts and comments of others', required: false, type: 'bool')]
    final public function update(Request $request, Community $community): JsonResponse
    {
        $community = app(UpdatesModeratorPermissions::class)->update($request->user() ?? throw new AuthenticationException, $community, $request->only(self::FIELDS));

        return self::success('Moderator rights updated', null, $community->toResource()->withLargeFields());
    }
}
