<?php

namespace App\Http\Controllers\Inertia;

use App\Contracts\InviteLinks\JoinsCommunitiesThroughInviteLinks;
use App\Enums\KnowiiInviteLinkJoinState;
use App\Exceptions\BusinessException;
use App\Exceptions\InviteLinkUnusableException;
use App\Models\InviteLink;
use App\Models\User;
use App\Support\InviteLinks;
use App\Support\InviteLinkTexts;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The way in through an invite link (#1391, ADR 0062). The address of a link (`/invite/{token}`) keeps the sha256 of the
 * token in the session and sends to the page of the link (`/invite`): the token does not stay in the address, and the
 * session never holds it. The page shows the community and what the person can do; "Join" is a POST to `/invite` that
 * reads the link of the session. Opening a link writes no membership, no count and no record: only the session (the hash,
 * and the intended address of a visitor).
 */
class InviteLinkController extends Controller
{
    /**
     * Where the session keeps the sha256 of the token of the last link that this browser opened
     */
    public const string SESSION_KEY = 'invite_link.token_hash';

    /**
     * The key of the error of a join on the page
     */
    public const string ERROR_KEY = 'join';

    public function open(Request $request, string $inviteLinkToken): RedirectResponse
    {
        $request->session()->put(self::SESSION_KEY, InviteLink::hashToken($inviteLinkToken));

        return redirect()->route('invite-links.show');
    }

    /**
     * The page of the link of the session. A link that does not work (or no link) gives the state `unusable` with status
     * 404 and no data of a community. The props hold the community and the state only: never the token, the name of the
     * link, its groups, its tags, its counts or its end. A member reads the page one time: the session forgets the link.
     */
    public function show(Request $request): Response
    {
        $tokenHash = $request->session()->get(self::SESSION_KEY);
        $link = is_string($tokenHash) ? InviteLinks::usableByHash($tokenHash) : null;

        JsonResource::withoutWrapping();

        // WARNING: aligned with InviteLinkPageProps (invite-link.schema.ts)
        if ($link === null) {
            return Inertia::render('InviteLinks/Show', [
                'community' => null,
                'state' => KnowiiInviteLinkJoinState::Unusable->value,
            ])->toResponse($request)->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        $user = $request->user();
        $state = $user instanceof User ? InviteLinks::stateFor($user, $link->community) : KnowiiInviteLinkJoinState::Join;
        if ($state === KnowiiInviteLinkJoinState::Member) {
            $request->session()->forget(self::SESSION_KEY);
        }

        return Inertia::render('InviteLinks/Show', [
            'community' => $link->community->toResource(),
            'state' => $state->value,
        ])->toResponse($request);
    }

    /**
     * "Join" on the page: the person joins through the link of the session. A link that does not work gives one refusal,
     * whatever the page sends. The page sends the cuid of its community: when
     * this browser opened another link after the page loaded (another tab), the join is refused and the page shows the
     * newer link. A success forgets the link of the session and goes to the community home; a refusal goes back to the page
     * with its text.
     *
     * @throws AuthenticationException
     */
    public function join(Request $request): RedirectResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $tokenHash = $request->session()->get(self::SESSION_KEY);
        // A link that does not work gives one answer, whatever the page sends: the community of that link stays unknown
        $link = is_string($tokenHash) ? InviteLinks::usableByHash($tokenHash) : null;
        if (! is_string($tokenHash) || $link === null) {
            return self::refused(InviteLinkTexts::UNUSABLE);
        }
        if ($link->community->cuid !== $request->string('community')->toString()) {
            return self::refused(InviteLinkTexts::ANOTHER_LINK);
        }

        try {
            $joined = app(JoinsCommunitiesThroughInviteLinks::class)->joinWithTokenHash($user, $tokenHash);
        } catch (InviteLinkUnusableException) {
            return self::refused(InviteLinkTexts::UNUSABLE);
        } catch (BusinessException $e) {
            return self::refused($e->getMessage());
        }

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('communities.show', ['communitySlug' => $joined->slug]);
    }

    /**
     * An outdated session (ADR 0059, `EndOutdatedSessions`) that opens a link: the new session keeps the hash of the token
     * and the page of the link as the intended address, never the address with the token, and the answer is the answer of
     * an outdated session (the sign-in page, or null for the JSON answer). After the sign-in, the person lands on the page.
     */
    public static function afterAnOutdatedSession(Request $request, AuthenticationException $e): ?RedirectResponse
    {
        $token = $request->route('inviteLinkToken');
        if (! is_string($token) || ! $request->hasSession()) {
            return null;
        }

        $request->session()->put(self::SESSION_KEY, InviteLink::hashToken($token));
        $redirectTo = $e->redirectTo($request);
        if ($request->expectsJson() || ! is_string($redirectTo) || $redirectTo === '') {
            return null;
        }

        $redirect = redirect();
        $redirect->setIntendedUrl(route('invite-links.show'));

        return $redirect->to($redirectTo);
    }

    private static function refused(string $message): RedirectResponse
    {
        return redirect()->route('invite-links.show')->withErrors([self::ERROR_KEY => $message]);
    }
}
