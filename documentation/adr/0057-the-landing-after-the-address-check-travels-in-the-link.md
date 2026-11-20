# 0057. The landing after the address check travels in the link

- Status: Accepted
- Date: 2026-11-20
- Issue: #1612
- Pull request: #1705

## Context

A visitor reads a page of a community, signs up, and opens the link of the address check. The session keeps the page as the intended address ([ADR 0044](0044-public-spaces-for-visitors.md), point 15, and "Who can join", point 4), so the person comes back to the page when the link opens in the same browser, with the same session. The address is lost in four cases:

1. The link opens in another browser or on another device (the browser of a mail application on a phone). The person signs in there first, the sign-in uses up the intended address (it held the link itself), and the address check answers with the dashboard.
2. The person presses "Log out" on the page of the address check.
3. The session ends before the click.
4. Before the click, the person opens another guarded address in the same browser, which replaces the intended address.

The sign-in link by email ([ADR 0023](0023-sign-in-links.md)) has the same gap in another browser. A sign-in with a password, with the second factor or with a provider ([ADR 0045](0045-openid-connect-sign-in.md), point 6) lives in one session, and has no gap.

The question: where does the destination live between the sign-up and the address check, and how is it sure that only a page of this instance is a destination.

## Decision

The destination (the "landing") travels inside the link that the person opens. The session stays the first source, and the link fills the gap when the session has nothing useful. No table and no column is added, and no screen changes. A sign-in link keeps its landing in the cache for the life of the link, beside the entry of the account (point 14).

### The landing value

1. `App\Support\Landing` is a final class with static functions. It holds no state and writes to no table.
2. `Landing::ROUTES` is the list of the named `GET` routes of the group `auth.optional` of `routes/web.php`: `communities.show`, `spaces.show`, `posts.show`, `pages.show`, `resources.show`, `events.show`. A comment on the group and on the constant says that they stay aligned, and a test compares the two.
3. `Landing::pathOf()` reads any text (the intended address of the session, the parameter of a link, a value of the cache), in this order:
   1. Null, an empty text or more than 2048 characters: null.
   2. It keeps only the path of the text. No path, or a path that does not start with `/`: null.
   3. It asks the routes of the application which one answers a `GET` of that path, in the order of the router. No route, a route without a name (the fallback route has none), or a name that is not in `ROUTES`: null. The parameters are bound to a copy of the route: the route of the request that runs is not changed.
   4. Each parameter of the route holds only the characters of a slug (`a-z`, `0-9`, `-`), and it is at most as long as the longest slug of the application: `Constants::MAX_LENGTH_SLUG` characters (`config/sluggable.php`), plus the number that makes a slug unique in its parent (`-2`), so 20 characters more. Else null. Every slug of the application is made of these characters: the slugs that the models make from a name, and the marker of a deleted post.
   5. The result is `route($name, $parameters, false)`: the path that the router builds from the name and the parameters.
4. `Landing::fromSession()` is `pathOf()` of the intended address of the session, or null without a session (a command, a job).
5. `Landing::remember()` (the address check) and `Landing::rememberForMember()` (the sign-in link) write an address to the intended address of the session, or do nothing (see "Where the person lands").

### No open redirect, by construction

6. A landing is never a full address. It is a path, and only a path that the router of this instance gives to one of the six routes.
7. The code never redirects to a text that it received. It asks for the name of the route and the parameters of the path, and it builds the address again with `route()`. The host, the scheme, the query and the fragment of the text are never used. The host of every address that the application builds comes from the configuration (`AnswerOnlyForOwnHostNames`).
8. A text that fails a check is dropped without an error, and the person gets the landing of before: the intended address of the session, else the dashboard.

### Where the landing travels

9. The session keeps the intended address as before: nothing changes in `AuthenticateIfPresent`, in `bootstrap/app.php`, and in the answers of Fortify for the sign-up and the sign-in. A sign-in with a password, with or without the second factor, and a sign-in with a provider answer as before. One thing is new in the same browser: the landing of a link replaces an intended address of the session that is not one of the six pages and not the link of an invitation (the dashboard, the settings of a community, the profile). Before, that address won.
10. The mail of the address check is `App\Notifications\VerifyEmailAddress`, which extends the notification of the framework. It builds the same signed, temporary route, with one more parameter, `landing`, when there is a landing. The words of the mail, its queue and the life of its link (60 minutes) do not change ([ADR 0054](0054-email-delivery-the-mail-queue-the-budget-bounces-and-the-one-click-stop.md), point 1). The value of `landing` is the path without its first slash, with `.` in the place of `/`: `community.x.space.y.post.z` (`Landing::linkValueOf()`). A slug holds no `.`, so the path comes back as it was. The value needs no escape, so the query of the link holds only letters, digits, `=`, `&`, `.` and `-`: it stays the same when a program on the way writes the escapes of a link again, and the signature, which covers the query as it is written, stays good. `Landing::pathOfLinkValue()` reads the value: it accepts only slugs and the fixed words of the routes with a `.` between them (no `/`, no `%`, no empty part), and every check of point 3 runs on the path that it gives. A value in another form, a path for example, gives no landing.
11. `User::sendEmailVerificationNotification()` sends it with `Landing::fromSession()`. This one place covers the sign-up, "Resend verification email" and a change of the address in the profile.
12. `App\Http\Responses\VerifyEmailResponse` replaces the answer of Fortify (`FortifyServiceProvider::register()` binds it). A request that wants JSON gets 204, as before. Else it calls `Landing::remember()` with the path of the `landing` of the query, and answers with the intended address, else `/dashboard?verified=1`, as before. A second click on the link gets the same answer.
13. A link with a changed `landing` fails the `signed` middleware: 403, and the address is not verified, as for any changed link. The check of point 3 still runs on a landing with a good signature.
14. The sign-in link:
    1. `SendsMagicLinks::send()` takes the landing (`MagicLinkController::store()` gives `Landing::fromSession()`). For an account that exists, `MagicLinks` keeps it in a second cache entry, `magic-link-landing:<the hash of the token>`, with the life of the token (15 minutes). The first entry keeps its shape, so a link that was sent before still works. `ConsumesMagicLinks::pullLanding()` reads and deletes the entry, and `consume()` deletes it too. An unknown address stores nothing.
    2. The landing is kept only for a member. When the link is used, `MagicLinkController::consume()` calls `Landing::rememberForMember()`: the landing counts only when the account is already a member of the community of the landing (`User::belongsToCommunity()`: the owner is a member). Else the link lands as before: the intended address of the session, else the dashboard. The reason: the person who asks for a sign-in link is not always the person who owns the address, so a sign-in link never takes an account to a community that it did not join. The link of the address check does not have this rule.
    3. With the second factor, nothing of the account is decided or written before the code is accepted. `consume()` starts the challenge of Fortify, and puts in the session only the path that `Landing::pathOf()` gives for the landing of the link (`login.landing`, beside the `login.id` of Fortify). The path depends on no account, and the intended address of the session does not change. `App\Http\Responses\TwoFactorLoginResponse` replaces the answer of Fortify for an accepted code or recovery code (`FortifyServiceProvider::register()` binds it). It reads and removes the path, calls `Landing::rememberForMember()` for the account that passed the challenge, and answers as Fortify does: the intended address, else the dashboard, and 204 for a request that wants JSON (then without a landing). A session without the path gets the answer of before.
    4. The path leaves the session when the code is accepted. Before that, two things remove or replace it in the same session: a sign-in link that works (with or without the second factor), and a new challenge, which a password (`App\Listeners\ForgetHeldLanding`, on the event `TwoFactorAuthenticationChallenged` of Fortify) or a provider (`OpenIdConnectController`) of an account with the second factor starts. Else the path stays in the session, with the `login.id` of Fortify, until the session ends: Fortify gives no event for a challenge that a person leaves, a wrong code is not the end of a challenge, a sign-in link that expired or that was used already changes nothing, and a sign-in of an account without the second factor, with a password or with a provider, starts no challenge. Nothing reads the path outside the answer of an accepted code, so a path that stays changes no landing.
15. The sign-in with a provider needs no landing of its own: its flow lives in one session.

### Where the person lands

16. `Landing::remember()` and `Landing::rememberForMember()` work in this order, and stop at the first point that applies. "It stops" means the landing of before.
    1. The session already holds a page of a community, or the link of an invitation ([ADR 0019](0019-community-invitations-by-email.md), point 3): the session wins. It is the newer intent of this browser, and the flow of an invitation stays as it is.
    2. The text of the link is not a landing.
    3. No community has the slug of the path, or the community is a personal one.
    4. For a sign-in link only (`rememberForMember()`): the account is not a member of the community (point 14.2).
    5. The path is the home of the community: it is the target.
    6. The path is a page in a space. When the space no longer exists in that community, or the person may not read it (`SpacePolicy::view`), the target is the home of the community. Else the target is the page.
    7. The target becomes the intended address of the session, as an address built with `route()`.
17. `Landing` only chooses an address. The page answers with its own rules, as before, and nothing joins by itself ([ADR 0044](0044-public-spaces-for-visitors.md), "Who can join", point 4). After the address check, each of these cases can come. After a sign-in link in another browser, only the first one can:
    1. A member already: the page, or the home of the community.
    2. An open community with a public page: the public page or the public space page, with "Join".
    3. A community by invitation with a public page: the public page, without "Join".
    4. A members-only space of a community with a public page: the public page of the community.
    5. A community without a public page: the page without access (403), with "Join" when the community is open, and the words about an invitation when it is not.
    6. A community that was deleted, or whose slug changed: the dashboard.
18. A join on request does not exist. When it comes, its action shows on the same public page, and this decision needs no change.

## Consequences

1. No table and no column: no change of the privacy policy and no change of the member data export. The landing has the life of the link that carries it. The landing of a sign-in link is an entry of the cache for the 15 minutes of the link, beside the entry that holds the id of the account (the same hash of the token). Both go when the link is used. With the default cache store (the database), the entries of a link that nobody used stay as expired rows until the daily `cache` step of `retention:prune` removes them.
2. One limit: a mail of the address check that is sent again from a browser whose session holds no page of a community carries no landing. That person lands on the dashboard, as before.
3. In the same browser, the landing of a link wins over an intended address of the session that is not a page of a community and not the link of an invitation (point 9). A person who opened the settings or the profile before the link lands on the page of the community, not on that address.
4. The cost of point 14.2: a person with an account who is not a member of the community, who asks for a sign-in link from a page of that community and opens the link in another browser, lands on the dashboard, as before this decision. In the same browser the session holds the page, and the person lands on it.
5. The link of the address check has no member rule (point 14.2), because a person who signs up is never a member yet: such a rule would remove every landing after a sign-up. So the account lands on the page that the person who signed up was reading, also when another person opens the link (the person who signs up is not always the person who owns the address). Accepted: the page is always one of the six pages of this instance (points 6 and 7), it answers with its own rules, and nobody joins (point 17).
6. With the second factor, a challenge that nobody finishes leaves the path of the link in its session (point 14.4): the path of one of the six pages, and nothing of the account. The next accepted code in that session checks it for the account of that code.
7. The link of the address check holds the landing in its query. The access log of a proxy in front of the application then holds it with the rest of the link, as it holds every link of a mail. The application writes no log line with a landing.
8. A link of the address check whose `landing` has another form than the one of point 10 gives no landing. No link with a landing was sent before this decision.
9. A post, a page, a resource or an event that was deleted while its space still exists answers 404 after the landing. Accepted: the link lives 60 minutes.
10. A slug that another community takes inside the life of a link leads to that community.
11. A new page for visitors is added to the group `auth.optional` and to `Landing::ROUTES` together (the test fails else), and each of its parameters is a slug. The fixed words of its path hold only the characters of a slug, so that the path has the form of point 10.
12. `Landing::fromSession()` is the value that the sign-in and sign-up pages can show later (#1571), and `Landing::remember()` is the place for a first page of new members (#1398).

## Alternatives considered

1. The session only (the behavior before): no new code, and it fails in the four cases of the context. Kept as the first source, refused as the only one.
2. Columns on the account (a community and a page, set at the sign-up, cleared at the first landing): it works from any browser and for a mail that is sent again from anywhere. It costs a migration, a new piece of personal data at rest (a change of the privacy policy, which is an approved text), the member data export, an action behind a contract for the write, and a rule for a value that is never used.
3. A `return` parameter on the sign-up and sign-in forms: [ADR 0044](0044-public-spaces-for-visitors.md) refused it ("Alternatives considered", point 2). It needs an allow-list in each form, and it leaves the other paths without it.
4. The link wins over the session: a person who opened another community in the same browser after the sign-up would land on the older page.
5. The landing of a sign-in link for every account, a member or not: the mail of a sign-in link names no community, and the person who opens it is not always the person who asked for it (point 14.2).
6. The landing of a sign-in link chosen before the code of the second factor, so that the answer of Fortify needs no change: the session would hold a choice that depends on the rights of the account before the account proved its second factor.
7. The landing as a path in the query of the link: the path needs escapes (`%2F`), the signature covers the query as it is written, and a link whose escapes a program wrote again would be refused.
