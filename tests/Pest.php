<?php

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Build a Guzzle handler stack that fakes the HTTP calls made by the text
 * resource creation flow (FetchUrl trait), in order:
 *   1. getFinalUrlAfterRedirects (GET) — no redirect history
 *   2. isUrlAvailable (HEAD)
 *   3. fetchUrl (POST to Browserless) — returns the given HTML
 *
 * The provided HTML should not reference a cover image, so no extra image
 * requests are made.
 */
function fakeTextResourceFetchHandler(string $html): HandlerStack
{
    return HandlerStack::create(new MockHandler([
        new Response(200, [], ''),
        new Response(200, [], ''),
        new Response(200, [], $html),
    ]));
}

/**
 * A community with one user per role, and a user outside of it.
 *
 * @return array{community: Community, owner: User, admin: User, moderator: User, member: User, outsider: User}
 */
function communityWithMembers(KnowiiCommunityVisibility $visibility = KnowiiCommunityVisibility::Private): array
{
    $owner = User::factory()->withUserProfile()->create();
    $community = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => $visibility]);

    $users = ['community' => $community, 'owner' => $owner];

    foreach (['admin' => KnowiiCommunityMemberRole::Admin, 'moderator' => KnowiiCommunityMemberRole::Moderator, 'member' => KnowiiCommunityMemberRole::Member] as $key => $role) {
        $user = User::factory()->withUserProfile()->create();
        $community->users()->attach($user, ['role' => $role]);
        $users[$key] = $user;
    }

    $users['outsider'] = User::factory()->withUserProfile()->create();

    /** @var array{community: Community, owner: User, admin: User, moderator: User, member: User, outsider: User} $users */
    return $users;
}
