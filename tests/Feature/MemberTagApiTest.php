<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityMemberRole;
use App\Models\Community;
use App\Models\MemberTag;
use App\Models\User;
use App\Support\MemberTagAssignments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
 * The routes of the member tags (#1389, ADR 0060), and the field `tags` of the member list. A tag of the managers does
 * not exist for a reader who does not see every tag: no name, no cuid, no count in any answer, and a 404 with the body of
 * a cuid that does not exist on each route of the tag.
 */

// Texts that no page holds by accident, with no character that JSON or HTML writes in another way
const MTAG_SECRET_NAME = 'Qxsecret Onboarded';
const MTAG_SHOWN_NAME = 'Qxshown Founder';

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    // A plain member who reads the tags of another member
    $this->reader = User::factory()->withUserProfile()->create(['username' => 'qx-reader', 'name' => 'Rita Qxreader']);
    $this->community->users()->attach($this->reader, ['role' => KnowiiCommunityMemberRole::Member]);

    $this->shown = MemberTag::factory()->forMembers()->create(['community_id' => $this->community->id, 'name' => MTAG_SHOWN_NAME, 'color' => 'purple']);
    $this->secret = MemberTag::factory()->forManagers()->create(['community_id' => $this->community->id, 'name' => MTAG_SECRET_NAME]);
    MemberTagAssignments::add($this->shown, $this->c['member']);
    MemberTagAssignments::add($this->secret, $this->c['member']);
});

function mtagPath(Community $community, ?string $tagCuid = null, string $route = ApiRoutes::COMMUNITY_MEMBER_TAGS, ?string $member = null): string
{
    return ApiRoutes::path($route, array_filter([
        ApiRoutes::PARAM_COMMUNITY => $community->cuid,
        ApiRoutes::PARAM_MEMBER_TAG => $tagCuid,
        ApiRoutes::PARAM_MEMBER => $member,
    ]));
}

function mtagMembersListPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]).'?limit=100';
}

function mtagAs(?User $user): TestCase
{
    app('auth')->forgetGuards();

    return $user === null ? test() : test()->actingAs($user->fresh() ?? $user, 'web');
}

/**
 * The next request comes from the reader through a session, or through a real API token with or without the abilities of
 * the member tags (the token has communities:read in both cases)
 */
function mtagThrough(User $reader, string $way): TestCase
{
    app('auth')->forgetGuards();
    if ($way === 'session') {
        return test()->withoutToken()->actingAs($reader->fresh() ?? $reader, 'web');
    }
    $abilities = $way === 'token with the abilities' ? ['communities:read', 'member-tags:read', 'member-tags:write'] : ['communities:read'];

    return test()->withToken($reader->createToken('qx-'.$way, $abilities)->plainTextToken);
}

function mtagLetModeratorsManageMembers(Community $community): void
{
    $community->update(['moderator_permissions' => [...$community->moderatorPermissions(), 'manage_members' => true]]);
}

/**
 * Every answer that a reader can get about the tags of the community and of the tagged member, as text: the member list,
 * the list of the tags, the member page of the tagged member, the details page and the directory, and each route of the
 * two tags (for a reader who may not change them: the writes are refused)
 *
 * @return array<string, string>
 */
function mtagEveryAnswer(TestCase $test, ?User $reader, bool $writes = true): array
{
    $c = $test->c;
    $community = $c['community'];
    $answers = [];
    $answers['member list'] = mtagAs($reader)->getJson(mtagMembersListPath($community))->getContent();
    $answers['tag list'] = mtagAs($reader)->getJson(mtagPath($community))->getContent();
    $answers['member page'] = mtagAs($reader)->get(route('members.show', ['communitySlug' => $community->slug, 'username' => $c['member']->username]))->getContent();
    $answers['about page'] = mtagAs($reader)->get(route('communities.about', ['communitySlug' => $community->slug]))->getContent();
    $answers['directory'] = mtagAs($reader)->get(route('members.index', ['communitySlug' => $community->slug]))->getContent();
    foreach ($writes ? [$test->shown, $test->secret] : [] as $tag) {
        $answers[$tag->name.' members'] = mtagAs($reader)->getJson(mtagPath($community, $tag->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS))->getContent();
        $answers[$tag->name.' add'] = mtagAs($reader)->postJson(mtagPath($community, $tag->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS), ['member' => $c['member']->cuid])->getContent();
        $answers[$tag->name.' update'] = mtagAs($reader)->patchJson(mtagPath($community, $tag->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG), ['name' => 'x'])->getContent();
        $answers[$tag->name.' remove'] = mtagAs($reader)->deleteJson(mtagPath($community, $tag->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBER, $c['outsider']->cuid))->getContent();
        $answers[$tag->name.' delete'] = mtagAs($reader)->deleteJson(mtagPath($community, $tag->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG))->getContent();
    }

    return $answers;
}

// ---------------------------------------------------------------------------------------------------------------------
// Who does what
// ---------------------------------------------------------------------------------------------------------------------

test('the three abilities of the member tags, by role (#1389)', function () {
    $community = $this->community;
    $abilities = static fn (User $user): array => [
        Gate::forUser($user->fresh())->check('manageMemberTags', $community->fresh()),
        Gate::forUser($user->fresh())->check('assignMemberTags', $community->fresh()),
        Gate::forUser($user->fresh())->check('viewMemberTags', $community->fresh()),
    ];

    expect($abilities($this->c['owner']))->toBe([true, true, true])
        ->and($abilities($this->c['admin']))->toBe([true, true, true])
        ->and($abilities($this->c['moderator']))->toBe([false, false, false])
        ->and($abilities($this->c['member']))->toBe([false, false, false])
        ->and($abilities($this->c['outsider']))->toBe([false, false, false]);

    mtagLetModeratorsManageMembers($community);
    expect($abilities($this->c['moderator']))->toBe([false, true, true])
        ->and($abilities($this->c['member']))->toBe([false, false, false]);
});

test('the owner and the admins define the tags; a moderator with the right puts them on any member, themself too (#1389)', function () {
    mtagLetModeratorsManageMembers($this->community);
    $moderator = $this->c['moderator'];

    mtagAs($moderator)->postJson(mtagPath($this->community), ['name' => 'Mods'])->assertForbidden();
    mtagAs($moderator)->patchJson(mtagPath($this->community, $this->shown->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG), ['name' => 'Renamed'])->assertForbidden();
    mtagAs($moderator)->deleteJson(mtagPath($this->community, $this->shown->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG))->assertForbidden();

    // No role hierarchy: the moderator tags the owner, an admin and themself
    $path = mtagPath($this->community, $this->secret->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS);
    foreach (['owner', 'admin', 'moderator'] as $role) {
        mtagAs($moderator)->postJson($path, ['member' => $this->c[$role]->cuid])->assertOk()->assertJsonPath('data.cuid', $this->c[$role]->cuid);
    }
    mtagAs($moderator)->deleteJson(mtagPath($this->community, $this->secret->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBER, $this->c['owner']->cuid))->assertNoContent();

    mtagAs($this->c['admin'])->postJson(mtagPath($this->community), ['name' => 'Speakers'])->assertCreated();
});

// ---------------------------------------------------------------------------------------------------------------------
// No leak of a tag of the managers
// ---------------------------------------------------------------------------------------------------------------------

test('a member, a moderator without the right, the tagged member and an outsider never get a tag of the managers (#1389)', function () {
    // The page "About" shows the team only: an admin has the tag too
    MemberTagAssignments::add($this->secret, $this->c['admin']);
    $readers = [
        'a member' => $this->reader,
        'a moderator without the right' => $this->c['moderator'],
        'the tagged member' => $this->c['member'],
        'an outsider' => $this->c['outsider'],
    ];

    foreach ($readers as $who => $reader) {
        foreach (mtagEveryAnswer($this, $reader) as $answer => $text) {
            expect(str_contains($text, MTAG_SECRET_NAME))->toBeFalse("{$who} gets the name in the {$answer}")
                ->and(str_contains($text, $this->secret->cuid))->toBeFalse("{$who} gets the cuid in the {$answer}");
        }
    }
    // Nothing changed
    expect($this->secret->fresh()?->name)->toBe(MTAG_SECRET_NAME)
        ->and($this->secret->members()->count())->toBe(2);

    // A member sees the tag of the members on the tagged member, the member themself too
    foreach ([$this->reader, $this->c['member']] as $reader) {
        $entry = collect(mtagAs($reader)->getJson(mtagMembersListPath($this->community))->assertOk()->json('data'))->firstWhere('cuid', $this->c['member']->cuid);
        expect(array_column($entry['tags'], 'name'))->toBe([MTAG_SHOWN_NAME])
            ->and($entry['tags'][0]['members_count'])->toBeNull();
    }
    // An outsider gets no list at all
    mtagAs($this->c['outsider'])->getJson(mtagMembersListPath($this->community))->assertForbidden();
    mtagAs($this->c['outsider'])->getJson(mtagPath($this->community))->assertForbidden();
});

test('the people who manage the members get every tag, in every answer (#1389)', function () {
    mtagLetModeratorsManageMembers($this->community);
    // The page "About" shows the team only: an admin has the tag too
    MemberTagAssignments::add($this->secret, $this->c['admin']);

    foreach (['owner', 'admin', 'moderator'] as $role) {
        $answers = mtagEveryAnswer($this, $this->c[$role], false);
        foreach (['member list', 'tag list', 'member page', 'about page', 'directory'] as $answer) {
            expect(str_contains($answers[$answer], MTAG_SECRET_NAME))->toBeTrue("{$role} misses the name in the {$answer}")
                ->and(str_contains($answers[$answer], $this->secret->cuid))->toBeTrue("{$role} misses the cuid in the {$answer}");
        }
    }

    $entry = collect(mtagAs($this->c['owner'])->getJson(mtagMembersListPath($this->community))->json('data'))->firstWhere('cuid', $this->c['member']->cuid);
    expect(array_column($entry['tags'], 'name'))->toBe([MTAG_SECRET_NAME, MTAG_SHOWN_NAME]);
});

test('a tag of the managers answers to another reader as a cuid that does not exist, on each route of a tag (#1389)', function (string $way) {
    $routes = [
        'members' => fn (string $cuid) => test()->getJson(mtagPath($this->community, $cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS)),
        'add' => fn (string $cuid) => test()->postJson(mtagPath($this->community, $cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS), ['member' => $this->reader->cuid]),
        'remove' => fn (string $cuid) => test()->deleteJson(mtagPath($this->community, $cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBER, $this->c['member']->cuid)),
        'update' => fn (string $cuid) => test()->patchJson(mtagPath($this->community, $cuid, ApiRoutes::COMMUNITY_MEMBER_TAG), ['name' => 'Taken over']),
        'delete' => fn (string $cuid) => test()->deleteJson(mtagPath($this->community, $cuid, ApiRoutes::COMMUNITY_MEMBER_TAG)),
    ];
    $readers = ['a member' => $this->reader, 'a moderator without the right' => $this->c['moderator'], 'the tagged member' => $this->c['member'], 'an outsider' => $this->c['outsider']];
    // A token without the abilities gets the 403 of the ability before the tag is read (ADR 0022), for both cuids
    $status = $way === 'token without the abilities' ? 403 : 404;

    foreach ($readers as $who => $reader) {
        foreach ($routes as $name => $route) {
            mtagThrough($reader, $way);
            /** @var TestResponse $secret */
            $secret = $route($this->secret->cuid);
            mtagThrough($reader, $way);
            /** @var TestResponse $missing */
            $missing = $route('qxnosuchtagq7x2qxnosucht');
            expect($secret->status())->toBe($status, "{$who}, {$name}: the status of the tag of the managers")
                ->and($missing->status())->toBe($status, "{$who}, {$name}: the status of a missing cuid")
                ->and($secret->getContent())->toBe($missing->getContent(), "{$who}, {$name}: the bodies differ");
        }
    }
    expect($this->secret->fresh()?->name)->toBe(MTAG_SECRET_NAME)->and($this->secret->members()->count())->toBe(1);
})->with(['session', 'token without the abilities', 'token with the abilities']);

test('with a token, the binding of a tag keeps the abilities and the rights of the managers (#1389, ADR 0022)', function () {
    $members = mtagPath($this->community, $this->secret->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS);

    // The owner sees the tag: a token without the ability gets the 403 of the ability, a token with it gets the members
    mtagThrough($this->c['owner'], 'token without the abilities')->getJson($members)->assertForbidden();
    mtagThrough($this->c['owner'], 'token with the abilities')->getJson($members)->assertOk()->assertJsonPath('data.*.cuid', [$this->c['member']->cuid]);
    // A member with the abilities sees a tag of the members, and gets the 403 of the policy on its members
    mtagThrough($this->reader, 'token with the abilities')->getJson(mtagPath($this->community, $this->shown->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS))->assertForbidden();
    // A tag of another community gives a 404 with a token too
    $other = communityWithMembers();
    mtagThrough($other['owner'], 'token with the abilities')->getJson(mtagPath($other['community'], $this->shown->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS))->assertNotFound();
});

test('a tag of another community gives a 404 (#1389)', function () {
    $other = communityWithMembers();

    mtagAs($other['owner'])->patchJson(mtagPath($other['community'], $this->shown->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG), ['name' => 'Mine'])->assertNotFound();
    mtagAs($other['owner'])->getJson(mtagPath($other['community'], $this->secret->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS))->assertNotFound();
});

test('the refusals of a write come in order: 404, 403, 400, then the limit (#1389)', function () {
    // 404 before 403: a member on a tag of the managers
    mtagAs($this->reader)->patchJson(mtagPath($this->community, $this->secret->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG), ['color' => 'pink'])->assertNotFound();
    // 403 before 400: a member on a tag of the members, with a wrong field
    mtagAs($this->reader)->patchJson(mtagPath($this->community, $this->shown->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG), ['color' => 'pink'])->assertForbidden();

    // 400 before the limit
    MemberTag::factory()->count(Constants::MAX_MEMBER_TAGS_PER_COMMUNITY - 2)->create(['community_id' => $this->community->id]);
    mtagAs($this->c['owner'])->postJson(mtagPath($this->community), ['name' => 'x'])->assertStatus(400);
    mtagAs($this->c['owner'])->postJson(mtagPath($this->community), ['name' => 'Speakers'])
        ->assertStatus(422)->assertJsonPath('message', 'A community can have at most 100 member tags');
});

// ---------------------------------------------------------------------------------------------------------------------
// The tags
// ---------------------------------------------------------------------------------------------------------------------

test('the list of the tags: every tag with its count for a manager, the tags of the members without a count for a member (#1389)', function () {
    MemberTag::factory()->forMembers()->create(['community_id' => $this->community->id, 'name' => 'alpha qx']);

    mtagAs($this->c['owner'])->getJson(mtagPath($this->community))->assertOk()
        ->assertJsonPath('data.*.name', ['alpha qx', MTAG_SECRET_NAME, MTAG_SHOWN_NAME])
        ->assertJsonPath('data.*.members_count', [0, 1, 1])
        ->assertJsonPath('data.2.color', 'purple')
        ->assertJsonPath('data.2.visibility', 'members');

    mtagAs($this->reader)->getJson(mtagPath($this->community))->assertOk()
        ->assertJsonPath('data.*.name', ['alpha qx', MTAG_SHOWN_NAME])
        ->assertJsonPath('data.*.members_count', [null, null]);
});

test('a create holds the defaults gray and managers, and refuses a wrong color or visibility (#1389)', function () {
    mtagAs($this->c['owner']);
    $response = $this->postJson(mtagPath($this->community), ['name' => 'Speakers'])->assertCreated();

    expect($response->json('data'))->toHaveKeys(['cuid', 'name', 'color', 'visibility', 'members_count', 'created_at'])
        ->and($response->json('data.color'))->toBe('gray')
        ->and($response->json('data.visibility'))->toBe('managers')
        ->and($response->json('data.members_count'))->toBe(0)
        ->and(array_keys($response->json('data')))->not->toContain('id', 'community_id');

    $this->postJson(mtagPath($this->community), ['name' => 'Pink', 'color' => 'magenta'])->assertStatus(400)->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);
    $this->postJson(mtagPath($this->community), ['name' => 'Public', 'visibility' => 'public'])->assertStatus(400);
    $this->postJson(mtagPath($this->community), ['name' => 'Blue', 'color' => 'blue', 'visibility' => 'members'])->assertCreated()
        ->assertJsonPath('data.color', 'blue')->assertJsonPath('data.visibility', 'members');
});

test('the name: its limits, its cleaning, and one name per community without the case (#1389)', function () {
    mtagAs($this->c['owner']);
    $create = fn (mixed $name, ?Community $community = null) => $this->postJson(mtagPath($community ?? $this->community), ['name' => $name]);

    $create('x')->assertStatus(400);
    $create(str_repeat('é', 33))->assertStatus(400);
    $create(str_repeat('é', 32))->assertCreated();
    $create("Two\nlines")->assertStatus(400);
    $create("A\ttab")->assertStatus(400);
    // A format character: a zero width space, a right-to-left override, a left-to-right mark, a soft hyphen (two tags would
    // look the same)
    $create("Qzshown\u{200B} Probe")->assertStatus(400)->assertJsonValidationErrorFor('name', 'errors');
    $create("\u{202E}redrO")->assertStatus(400);
    $create("Foun\u{200E}der")->assertStatus(400);
    $create("Fou\u{00AD}nder")->assertStatus(400);
    $create('Qzshown Probe')->assertCreated();
    $create(['Founder'])->assertStatus(400);
    $create('   ')->assertStatus(400);
    $create('  Early    bird  ')->assertCreated()->assertJsonPath('data.name', 'Early bird');

    $create('Founder')->assertCreated();
    $create('founder')->assertStatus(400)->assertJsonPath('errors.name.0', 'A tag with this name exists already.');
    $create(' FOUNDER ')->assertStatus(400);

    // The same name in another community
    $other = Community::factory()->create(['owner_id' => $this->c['owner']->id]);
    $create('Founder', $other)->assertCreated();

    // A rename to its own name in another case
    $founder = MemberTag::query()->where('community_id', $this->community->id)->where('name', 'Founder')->firstOrFail();
    $this->patchJson(mtagPath($this->community, $founder->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG), ['name' => 'FOUNDER'])->assertOk()->assertJsonPath('data.name', 'FOUNDER');
    $this->patchJson(mtagPath($this->community, $founder->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG), ['name' => 'early BIRD'])->assertStatus(400);
});

test('an update changes the fields of the request only, and a rename keeps the cuid and the members (#1389)', function () {
    mtagAs($this->c['admin'])->patchJson(mtagPath($this->community, $this->secret->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG), ['name' => 'Welcomed'])
        ->assertOk()
        ->assertJsonPath('data.cuid', $this->secret->cuid)
        ->assertJsonPath('data.name', 'Welcomed')
        ->assertJsonPath('data.color', 'gray')
        ->assertJsonPath('data.visibility', 'managers')
        ->assertJsonPath('data.members_count', 1);

    mtagAs($this->c['admin'])->patchJson(mtagPath($this->community, $this->secret->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG), ['visibility' => 'members', 'color' => 'teal'])
        ->assertOk()->assertJsonPath('data.name', 'Welcomed')->assertJsonPath('data.visibility', 'members')->assertJsonPath('data.color', 'teal');

    // Now the members see it
    $entry = collect(mtagAs($this->reader)->getJson(mtagMembersListPath($this->community))->json('data'))->firstWhere('cuid', $this->c['member']->cuid);
    expect(array_column($entry['tags'], 'name'))->toBe([MTAG_SHOWN_NAME, 'Welcomed']);
});

test('a delete answers 204 and removes the tag from every member (#1389)', function () {
    MemberTagAssignments::add($this->secret, $this->c['admin']);

    mtagAs($this->c['owner'])->deleteJson(mtagPath($this->community, $this->secret->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG))->assertNoContent();

    expect(MemberTag::query()->whereKey($this->secret->id)->exists())->toBeFalse()
        ->and(DB::table('member_tag_members')->where('member_tag_id', $this->secret->id)->count())->toBe(0)
        ->and(DB::table('member_tag_members')->where('member_tag_id', $this->shown->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------------------------------------------------
// The members of a tag
// ---------------------------------------------------------------------------------------------------------------------

test('a member is added by cuid or by username, once, and only a member of the community (#1389)', function () {
    $path = mtagPath($this->community, $this->shown->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS);
    mtagAs($this->c['admin']);

    $this->postJson($path, ['member' => $this->reader->cuid])->assertOk()->assertJsonPath('data.username', 'qx-reader');
    $this->postJson($path, ['username' => 'qx-reader'])->assertOk();
    expect(DB::table('member_tag_members')->where('member_tag_id', $this->shown->id)->where('user_id', $this->reader->id)->count())->toBe(1);

    // The owner counts as a member
    $this->postJson($path, ['username' => $this->c['owner']->username])->assertOk();

    $this->postJson($path, ['member' => $this->c['outsider']->cuid])->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value)
        ->assertJsonPath('errors.member.0', 'No member of the community matches');
    $this->postJson($path, ['username' => 'qx-nobody-here'])->assertStatus(400)->assertJsonPath('errors.username.0', 'No member of the community matches');
    $this->postJson($path, ['member' => $this->reader->cuid, 'username' => 'qx-reader'])->assertStatus(400);
    $this->postJson($path, ['email' => $this->reader->email])->assertStatus(400);
    $this->postJson($path, [])->assertStatus(400);

    expect($this->shown->members()->count())->toBe(3);
});

test('a remove answers 204, and 404 for a member without the tag (#1389)', function () {
    mtagAs($this->c['owner']);

    $this->deleteJson(mtagPath($this->community, $this->shown->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBER, $this->reader->cuid))->assertNotFound();
    $this->deleteJson(mtagPath($this->community, $this->shown->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBER, 'qxnosuchmember'))->assertNotFound();
    $this->deleteJson(mtagPath($this->community, $this->shown->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBER, $this->c['member']->cuid))->assertNoContent();

    expect($this->shown->members()->count())->toBe(0)->and($this->secret->members()->count())->toBe(1);
});

test('the 21st tag of a member is refused (#1389)', function () {
    $tags = MemberTag::factory()->count(Constants::MAX_MEMBER_TAGS_PER_MEMBER - 2)->create(['community_id' => $this->community->id]);
    foreach ($tags as $tag) {
        MemberTagAssignments::add($tag, $this->c['member']);
    }
    $extra = MemberTag::factory()->create(['community_id' => $this->community->id]);
    mtagAs($this->c['owner']);

    // The 20 tags of the member: the same one again changes nothing
    $this->postJson(mtagPath($this->community, $this->shown->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS), ['member' => $this->c['member']->cuid])->assertOk();
    $this->postJson(mtagPath($this->community, $extra->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS), ['member' => $this->c['member']->cuid])
        ->assertStatus(422)->assertJsonPath('message', 'A member can have at most 20 tags');

    // The tags of another community do not count
    $other = Community::factory()->create(['owner_id' => $this->c['member']->id]);
    expect(MemberTagAssignments::add(MemberTag::factory()->create(['community_id' => $other->id]), $this->c['member']))->toBeTrue()
        ->and(DB::table('member_tag_members')->where('user_id', $this->c['member']->id)->count())->toBe(21);
});

test('the members of a tag: by name, by pages, for the managers only (#1389)', function () {
    foreach (['Cleo Qxc', 'Abel Qxa', 'Bea Qxb'] as $i => $name) {
        $user = User::factory()->withUserProfile()->create(['username' => 'qx-list-'.$i, 'name' => $name]);
        $this->community->users()->attach($user, ['role' => KnowiiCommunityMemberRole::Member]);
        MemberTagAssignments::add($this->shown, $user);
    }
    $this->c['member']->forceFill(['name' => 'Zed Qxz'])->save();
    $path = mtagPath($this->community, $this->shown->cuid, ApiRoutes::COMMUNITY_MEMBER_TAG_MEMBERS);

    mtagAs($this->c['owner'])->getJson($path.'?limit=2')->assertOk()
        ->assertJsonPath('data.*.name', ['Abel Qxa', 'Bea Qxb'])
        ->assertJsonPath('metadata.pagination.totalCount', 4)
        ->assertJsonPath('metadata.pagination.nextOffset', 2);
    mtagAs($this->c['owner'])->getJson($path.'?limit=2&offset=2')->assertOk()
        ->assertJsonPath('data.*.name', ['Cleo Qxc', 'Zed Qxz']);
    expect(array_keys(mtagAs($this->c['owner'])->getJson($path)->json('data.0')))->toBe(['cuid', 'name', 'username', 'avatar_url']);

    // A member does not list the members of a tag of the members (until the directory, T4)
    mtagAs($this->reader)->getJson($path)->assertForbidden();
});

// ---------------------------------------------------------------------------------------------------------------------
// The member list and its cost
// ---------------------------------------------------------------------------------------------------------------------

test('an entry of the member list holds its tags, by name without the case, and an empty list without tags (#1389)', function () {
    $lower = MemberTag::factory()->forMembers()->create(['community_id' => $this->community->id, 'name' => 'aaa qx first']);
    MemberTagAssignments::add($lower, $this->c['member']);

    $data = collect(mtagAs($this->c['owner'])->getJson(mtagMembersListPath($this->community))->assertOk()->json('data'));
    $entry = $data->firstWhere('cuid', $this->c['member']->cuid);

    expect(array_column($entry['tags'], 'name'))->toBe(['aaa qx first', MTAG_SECRET_NAME, MTAG_SHOWN_NAME])
        ->and(array_keys($entry['tags'][0]))->toBe(['cuid', 'name', 'color', 'visibility', 'members_count', 'created_at'])
        ->and($data->firstWhere('cuid', $this->reader->cuid)['tags'])->toBe([]);
});

test('the member list costs the same number of queries with 3 members and with 30, with their tags (#1389)', function () {
    $owner = User::factory()->withUserProfile()->create(['username' => 'qx-cost-owner', 'name' => 'Olive Qxowner']);
    $community = Community::factory()->create(['owner_id' => $owner->id]);
    $tags = [
        MemberTag::factory()->forMembers()->create(['community_id' => $community->id, 'name' => 'Qx one']),
        MemberTag::factory()->forManagers()->create(['community_id' => $community->id, 'name' => 'Qx two']),
    ];
    $join = function (int $i) use ($community, $tags): User {
        $user = User::factory()->withUserProfile()->create(['username' => sprintf('qx-cost-%02d', $i), 'name' => sprintf('Member Qxcost %02d', $i)]);
        $community->users()->attach($user, ['role' => KnowiiCommunityMemberRole::Member]);
        foreach ($tags as $tag) {
            MemberTagAssignments::add($tag, $user);
        }

        return $user;
    };
    $join(1);
    $join(2);

    $count = function (User $reader) use ($community): int {
        mtagAs($reader);
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->getJson(mtagMembersListPath($community))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $reader = User::query()->where('username', 'qx-cost-01')->firstOrFail();
    // The first visit of a day writes the date of the visit of the account: it is not a query of the list
    $count($owner);
    $count($reader);

    $ownerWith3 = $count($owner);
    $memberWith3 = $count($reader);
    for ($i = 3; $i <= 29; $i++) {
        $join($i);
    }
    mtagAs($owner)->getJson(mtagMembersListPath($community))->assertOk()->assertJsonCount(30, 'data')->assertJsonCount(2, 'data.1.tags');

    expect($count($owner))->toBe($ownerWith3)
        ->and($count($reader))->toBe($memberWith3);
});

// ---------------------------------------------------------------------------------------------------------------------
// Tokens
// ---------------------------------------------------------------------------------------------------------------------

test('a token needs member-tags:read to read the tags and member-tags:write to change them (#1389, ADR 0022)', function () {
    $path = mtagPath($this->community);

    Sanctum::actingAs($this->c['owner'], ['communities:read', 'groups:read', 'groups:write']);
    $this->getJson($path)->assertForbidden();
    $this->postJson($path, ['name' => 'By token'])->assertForbidden();
    // The member list still holds the tags with communities:read
    $this->getJson(mtagMembersListPath($this->community))->assertOk();

    Sanctum::actingAs($this->c['owner'], ['member-tags:read']);
    $this->getJson($path)->assertOk();
    $this->postJson($path, ['name' => 'By token'])->assertForbidden();

    Sanctum::actingAs($this->c['owner'], ['member-tags:write']);
    $this->postJson($path, ['name' => 'By token'])->assertCreated();
});

test('member-tags:read and member-tags:write are not in the default set of a new token (#1389, ADR 0022)', function () {
    expect(Jetstream::$defaultPermissions)->not->toContain('member-tags:read')->not->toContain('member-tags:write')
        ->and(Jetstream::$permissions)->toContain('member-tags:read', 'member-tags:write');
});
