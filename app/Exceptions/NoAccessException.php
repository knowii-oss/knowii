<?php

namespace App\Exceptions;

use App\Enums\KnowiiNoAccessKind;
use App\Models\Community;
use App\Support\PublicCommunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Jetstream\Jetstream;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * A signed-in person who is not a member may not read a page of a community (#1561): the answer stays a 403, with a page
 * that says why and offers the next step, in every environment. Thrown by `NoAccess`, never for a visitor, a member or a
 * request that expects JSON: they keep the answer of before.
 *
 * The page names the community only when this person may see its public page (the rule of `PublicCommunity`), and then
 * sends only the public shape of the community, as the public page does. Else the props hold nothing of the community.
 * Never the name of the space, the post, the page, the resource or a member.
 */
final class NoAccessException extends AccessDeniedHttpException
{
    public function __construct(private readonly Community $community, private readonly KnowiiNoAccessKind $kind)
    {
        parent::__construct('Only the members of the community may read this page');
    }

    public function render(Request $request): Response
    {
        JsonResource::withoutWrapping();

        $showsCommunity = PublicCommunity::showsPublicPage($request->user(), $this->community);

        // WARNING: The props passed here must remain aligned with NoAccessPageProps (community.schema.ts)
        return Jetstream::inertia()->render($request, 'Communities/NoAccess', [
            'kind' => $this->kind->value,
            'community' => $showsCommunity ? $this->community->toResource() : null,
        ])->toResponse($request)->setStatusCode(Response::HTTP_FORBIDDEN);
    }
}
