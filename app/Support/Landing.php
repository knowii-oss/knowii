<?php

namespace App\Support;

use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Facades\URL;

/**
 * The landing (#1612, ADR 0057): the page of a community that a person was reading when they signed up or asked for a
 * sign-in link. The session keeps it as the intended address (ADR 0044, point 15). The link of the address check and the
 * sign-in link carry it too, so that the person lands in the community when the link opens in another browser.
 *
 * A landing is never a full address and never a text that the application received. It is a path that the router of this
 * instance gives to one of the routes of ROUTES, and the code builds it again from the name of the route and its
 * parameters. The host, the scheme, the query and the fragment of the text are never used. A text that fails a check
 * gives null, without an error: the person then lands as before (the address of the session, else the dashboard).
 *
 * The class holds no state and writes to no table. It chooses an address only: the page answers with its own rules, and
 * nobody joins a community here.
 */
final class Landing
{
    /**
     * The pages that a visitor may open: the named GET routes of the group `auth.optional` of routes/web.php.
     *
     * WARNING: this list must remain aligned with that group. tests/Feature/LandingTest.php compares the two.
     */
    public const array ROUTES = ['communities.show', 'spaces.show', 'posts.show', 'pages.show', 'resources.show', 'events.show'];

    /**
     * The home of a community: the landing when the page of the link is not for this person
     */
    public const string HOME_ROUTE = 'communities.show';

    /**
     * The link of an invitation (ADR 0019): the session that holds it keeps it
     */
    public const string INVITATION_ROUTE = 'community-invitations.accept';

    /**
     * The name of the landing in the query of the link of the address check. Its value is the path with "." in the place of
     * "/" and without the first slash (linkValueOf): no character of it needs an escape in an address
     */
    public const string LINK_PARAMETER = 'landing';

    /**
     * A longer text is not read
     */
    public const int MAX_LENGTH = 2048;

    /**
     * Where the session keeps the intended address (the key of the framework)
     */
    private const string SESSION_KEY = 'url.intended';

    private const string COMMUNITY_PARAMETER = 'communitySlug';

    private const string SPACE_PARAMETER = 'spaceSlug';

    /**
     * Where the session keeps the landing of a sign-in link while the challenge of the second factor runs, beside the
     * `login.id` of Fortify. The value is a path of pathOf(): it depends on no account
     */
    private const string HELD_SESSION_KEY = 'login.landing';

    /**
     * The characters of a slug, for each parameter of a landing
     */
    private const string SLUG_PATTERN = '/\A[a-z0-9-]+\z/';

    /**
     * The longest parameter of a landing: the longest slug that the application makes. A slug has at most
     * Constants::MAX_LENGTH_SLUG characters (config/sluggable.php), plus the number that makes it unique in its parent
     * ("-2"): a separator and the digits of an integer (19 at most)
     */
    private const int MAX_LENGTH_PARAMETER = Constants::MAX_LENGTH_SLUG + 20;

    /**
     * The value of a landing in a link: slugs and the fixed words of the routes, with a "." between them
     */
    private const string LINK_VALUE_PATTERN = '/\A[a-z0-9-]+(?:\.[a-z0-9-]+)*\z/';

    /**
     * The landing of any text (the address of the session, the parameter of a link, a value of the cache), or null.
     * The result holds no character of the text by another way than the parameters of the route, which are slugs.
     */
    public static function pathOf(?string $address): ?string
    {
        $page = self::pageOf(self::routeOf($address));

        return $page === null ? null : route($page['name'], $page['parameters'], false);
    }

    /**
     * The landing of the intended address of the session, or null (also without a session: a command, a job)
     */
    public static function fromSession(): ?string
    {
        return self::pathOf(self::intended(request()));
    }

    /**
     * The landing as the link of the address check carries it, or null: the path of pathOf() without its first slash, with
     * "." in the place of "/" ("community.x.space.y.post.z"). A slug holds no ".", so the path comes back as it was. The
     * value needs no escape, so the query of the link stays the same when a program writes its escapes again.
     */
    public static function linkValueOf(?string $address): ?string
    {
        $path = self::pathOf($address);
        if ($path === null) {
            return null;
        }

        $value = str_replace('/', '.', ltrim($path, '/'));

        // Only a value that reads back as the same landing goes in a link
        return self::pathOfLinkValue($value) === $path ? $value : null;
    }

    /**
     * The landing of the value that a link of the address check carries, or null. The value holds only the characters of
     * LINK_VALUE_PATTERN (so no "/", no "%" and no empty part), and every check of pathOf() runs on the path that it gives.
     */
    public static function pathOfLinkValue(?string $value): ?string
    {
        if ($value === null || strlen($value) > self::MAX_LENGTH || preg_match(self::LINK_VALUE_PATTERN, $value) !== 1) {
            return null;
        }

        return self::pathOf('/'.str_replace('.', '/', $value));
    }

    /**
     * After the link of the address check proved who the person is: the page of the link becomes the intended address of
     * the session, and the answer of the address check goes there. It stops at the first point that applies:
     *
     * 1. The session already holds a page of a community, or the link of an invitation: it wins (it is the newer intent of
     *    this browser, and the flow of an invitation stays as it is).
     * 2. The text is not a landing.
     * 3. No community has the slug, or it is a personal community.
     * 4. The home of the community is the target.
     * 5. A page in a space is the target when the space exists and the person may read it. Else the home of the community.
     *
     * "It stops" means the landing of before: the address of the session when it has one, else the dashboard.
     */
    public static function remember(Request $request, User $user, ?string $address): void
    {
        self::intend($request, $user, $address, false);
    }

    /**
     * After a sign-in link signed the person in: the same as remember(), with one more stop after point 3: the account is
     * not a member of the community (the owner is a member). The person who asks for a sign-in link is not always the
     * person who owns the address, so a sign-in link never takes an account to a community that it did not join.
     */
    public static function rememberForMember(Request $request, User $user, ?string $address): void
    {
        self::intend($request, $user, $address, true);
    }

    /**
     * A sign-in link of an account with the second factor: the session keeps the path of the landing of the link until the
     * code is accepted, and replaces the one of an earlier challenge. Only pathOf() runs here: nothing is read of the
     * account, and the intended address of the session does not change.
     */
    public static function holdForChallenge(Request $request, ?string $address): void
    {
        self::forgetHeld($request);

        $path = self::pathOf($address);
        if ($path !== null && $request->hasSession()) {
            $request->session()->put(self::HELD_SESSION_KEY, $path);
        }
    }

    /**
     * The landing that the session kept for the challenge of the second factor, read and removed at once, or null
     */
    public static function pullHeld(Request $request): ?string
    {
        $path = $request->hasSession() ? $request->session()->pull(self::HELD_SESSION_KEY) : null;

        return is_string($path) ? $path : null;
    }

    /**
     * Removes the landing that the session kept for a challenge of the second factor: another sign-in started
     */
    public static function forgetHeld(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(self::HELD_SESSION_KEY);
        }
    }

    /**
     * The steps of remember() and of rememberForMember()
     */
    private static function intend(Request $request, User $user, ?string $address, bool $onlyForMember): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $held = self::routeOf(self::intended($request));
        if (self::pageOf($held) !== null || $held?->getName() === self::INVITATION_ROUTE) {
            return;
        }

        $page = self::pageOf(self::routeOf($address));
        if ($page === null) {
            return;
        }

        $community = Community::query()->where('slug', $page['parameters'][self::COMMUNITY_PARAMETER] ?? '')->first();
        if ($community === null || $community->visibility === KnowiiCommunityVisibility::Personal) {
            return;
        }

        if ($onlyForMember && ! $user->belongsToCommunity($community)) {
            return;
        }

        $target = route(self::HOME_ROUTE, [self::COMMUNITY_PARAMETER => $community->slug]);

        $spaceSlug = $page['parameters'][self::SPACE_PARAMETER] ?? null;
        if ($page['name'] !== self::HOME_ROUTE && $spaceSlug !== null) {
            $space = $community->spaces()->where('slug', $spaceSlug)->first();

            if ($space !== null && Gate::forUser($user)->allows('view', $space)) {
                $target = route($page['name'], $page['parameters']);
            }
        }

        $request->session()->put(self::SESSION_KEY, $target);
    }

    /**
     * The intended address of the session, or null
     */
    private static function intended(Request $request): ?string
    {
        $intended = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return is_string($intended) ? $intended : null;
    }

    /**
     * The name and the parameters of a route of ROUTES, or null: no route, a route that is not in the list, or a
     * parameter that holds another character than the ones of a slug, or that is longer than a slug. Every slug of the
     * application is made of these characters (the slugs that the models make from a name, and the marker of a deleted
     * post).
     *
     * @return array{name: string, parameters: array<string, string>}|null
     */
    private static function pageOf(?Route $route): ?array
    {
        $name = $route?->getName();
        if ($route === null || $name === null || ! in_array($name, self::ROUTES, true)) {
            return null;
        }

        $parameters = [];
        foreach ($route->parameterNames() as $parameter) {
            $value = is_string($parameter) ? $route->parameter($parameter) : null;
            if (! is_string($parameter) || ! is_string($value) || strlen($value) > self::MAX_LENGTH_PARAMETER || preg_match(self::SLUG_PATTERN, $value) !== 1) {
                return null;
            }

            $parameters[$parameter] = $value;
        }

        return ['name' => $name, 'parameters' => $parameters];
    }

    /**
     * The route that answers a GET of the path of the text, with the parameters of that path, or null: no text, a text
     * that is too long, no path, a path that does not start with "/", or a path that no route answers but the fallback
     * route (which has no name).
     */
    private static function routeOf(?string $address): ?Route
    {
        if ($address === null || $address === '' || strlen($address) > self::MAX_LENGTH) {
            return null;
        }

        $path = parse_url($address, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/')) {
            return null;
        }

        // A request for the path alone, on the host of the application. A REQUEST_URI that starts with "/" is read as it
        // is: no part of the text becomes a host
        $host = parse_url(URL::to('/'), PHP_URL_HOST);
        $probe = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path, 'HTTP_HOST' => is_string($host) ? $host : 'localhost']);

        // The order of the router: the first route that answers, and the fallback route last (it has no name: it is no
        // landing). matches() changes nothing, and the parameters are bound to a copy: the routes of the collection are
        // shared with the request that runs, which keeps its own parameters
        foreach (Router::getRoutes()->get('GET') as $route) {
            if (! $route->isFallback && $route->matches($probe)) {
                return (clone $route)->bind($probe);
            }
        }

        return null;
    }
}
