<?php

use App\ApiRoutes;
use App\Contracts\CommunityMembers\LeavesCommunities;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityVisibility;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Exceptions\BusinessException;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/*
 * Leave a community (#1338).
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    Event::fake([CommunityMembersChanged::class]);
});

function membershipPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERSHIP, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

function stillBelongsTo(User $user, Community $community): bool
{
    // actingAs() keeps the model in memory, with its cached communities
    return User::query()->findOrFail($user->id)->belongsToCommunity($community);
}

test('admins, moderators and members leave the community (#1338)', function (string $who) {
    $this->actingAs($this->c[$who])->deleteJson(membershipPath($this->c['community']))->assertNoContent();

    expect(stillBelongsTo($this->c[$who], $this->c['community']))->toBeFalse();
    expect($this->c['community']->getMemberById($this->c[$who]->id))->toBeNull();
    Event::assertDispatched(CommunityMembersChanged::class, fn (CommunityMembersChanged $event) => $event->communityCuid === $this->c['community']->cuid);

    // The community is closed to them from now on
    $this->app['auth']->forgetGuards();
    $this->actingAs(User::query()->findOrFail($this->c[$who]->id))
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]))
        ->assertForbidden();
})->with(['admin', 'moderator', 'member']);

test('the owner cannot leave: they transfer the ownership first (#1338)', function () {
    $this->actingAs($this->c['owner'])
        ->deleteJson(membershipPath($this->c['community']))
        ->assertStatus(422)
        ->assertJsonPath('type', KnowiiApiResponseType::BusinessIssue->value);

    expect(stillBelongsTo($this->c['owner'], $this->c['community']))->toBeTrue();
    expect($this->c['community']->fresh()->owner_id)->toBe($this->c['owner']->id);
    Event::assertNotDispatched(CommunityMembersChanged::class);
});

test('the owner of a personal community cannot leave it (#1338)', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $personal = $user->ownedCommunities()->where('visibility', KnowiiCommunityVisibility::Personal)->firstOrFail();

    $this->actingAs($user)->deleteJson(membershipPath($personal))->assertStatus(422);

    expect($personal->fresh())->not->toBeNull();
});

test('someone who is not a member cannot leave, and an unknown community gives a 404 (#1338)', function () {
    $this->actingAs($this->c['outsider'])->deleteJson(membershipPath($this->c['community']))->assertForbidden();
    $this->actingAs($this->c['outsider'])
        ->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERSHIP, [ApiRoutes::PARAM_COMMUNITY => 'unknowncuid']))
        ->assertNotFound();

    Event::assertNotDispatched(CommunityMembersChanged::class);
});

test('leaving a second time gives a 403 (#1338)', function () {
    $this->actingAs($this->c['member'])->deleteJson(membershipPath($this->c['community']))->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->actingAs(User::query()->findOrFail($this->c['member']->id))->deleteJson(membershipPath($this->c['community']))->assertForbidden();
});

test('leaving needs a session or a token with communities:write, and a verified address (#1338)', function () {
    $this->deleteJson(membershipPath($this->c['community']))->assertUnauthorized();

    Sanctum::actingAs($this->c['member'], ['communities:read']);
    $this->deleteJson(membershipPath($this->c['community']))->assertForbidden();
    expect(stillBelongsTo($this->c['member'], $this->c['community']))->toBeTrue();

    $this->c['moderator']->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($this->c['moderator'])->deleteJson(membershipPath($this->c['community']))->assertForbidden();
    expect(stillBelongsTo($this->c['moderator'], $this->c['community']))->toBeTrue();

    Sanctum::actingAs($this->c['member'], ['communities:write']);
    $this->deleteJson(membershipPath($this->c['community']))->assertNoContent();
    expect(stillBelongsTo($this->c['member'], $this->c['community']))->toBeFalse();
});

test('a member who leaves loses their access groups, and what they wrote stays (#1338)', function () {
    $community = $this->c['community'];
    $member = $this->c['member'];
    $group = AccessGroup::query()->create(['community_id' => $community->id, 'name' => 'Paid members']);
    $group->members()->attach($member);
    $group->members()->attach($this->c['moderator']);
    $space = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $community->id]))->create();
    $post = Post::factory()->inSpace($space)->create(['author_id' => $member->id]);

    // Another community of the same member is not touched
    $other = Community::factory()->create(['owner_id' => $this->c['outsider']->id, 'visibility' => KnowiiCommunityVisibility::Private]);
    $other->users()->attach($member, ['role' => 'member']);
    $otherGroup = AccessGroup::query()->create(['community_id' => $other->id, 'name' => 'Elsewhere']);
    $otherGroup->members()->attach($member);

    $this->actingAs($member)->deleteJson(membershipPath($community))->assertNoContent();

    expect($group->members()->pluck('users.id')->all())->toBe([$this->c['moderator']->id]);
    expect($post->fresh())->not->toBeNull();
    expect($post->fresh()->author_id)->toBe($member->id);
    expect(stillBelongsTo($member, $other))->toBeTrue();
    expect($otherGroup->members()->count())->toBe(1);
});

test('the action refuses the owner and outsiders by itself (#1338, ADR 0006)', function () {
    $action = app(LeavesCommunities::class);

    expect(fn () => $action->leave($this->c['owner'], $this->c['community']))->toThrow(BusinessException::class);
    expect(fn () => $action->leave($this->c['outsider'], $this->c['community']))->toThrow(AuthorizationException::class);
});
