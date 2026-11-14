<?php

use App\ApiRoutes;
use App\Constants;
use App\Contracts\Communities\TransfersCommunityOwnership;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Mail\CommunityOwnershipTransferred;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\User;
use App\Support\CommunityRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

/*
 * Transfer the ownership of a community (#1339, ADR 0035).
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    Event::fake([CommunityMembersChanged::class]);
    Mail::fake();
});

function ownershipPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_OWNERSHIP, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

/**
 * The body of a transfer: the factory password of every test user is "password".
 *
 * @return array{member: string, password: string}
 */
function ownershipBody(User $member, string $password = 'password'): array
{
    return ['member' => $member->cuid, 'password' => $password];
}

function reloadedUser(User $user): User
{
    // actingAs() keeps the model in memory, with its cached communities
    return User::query()->findOrFail($user->id);
}

test('the owner gives the community to an admin and becomes an admin (#1339)', function () {
    $community = $this->c['community'];
    $owner = $this->c['owner'];
    $admin = $this->c['admin'];

    $this->actingAs($owner)
        ->postJson(ownershipPath($community), ownershipBody($admin))
        ->assertOk()
        ->assertJsonPath('type', KnowiiApiResponseType::Success->value)
        ->assertJsonPath('data.cuid', $community->cuid)
        ->assertJsonPath('data.member_count', 4)
        ->assertJsonMissingPath('data.owner_id');

    $community->refresh();
    expect($community->owner_id)->toBe($admin->id);
    // The owner is never a row of the member table
    expect($community->getMemberById($admin->id))->toBeNull();
    expect($community->getMemberById($owner->id)?->role)->toBe(KnowiiCommunityMemberRole::Admin);
    expect(CommunityRoles::roleName(reloadedUser($admin), $community))->toBe('owner');
    expect(CommunityRoles::roleName(reloadedUser($owner), $community))->toBe('admin');
    // Nobody joined or left: the owner, the former owner, the moderator and the member
    expect($community->users()->count())->toBe(3);

    Mail::assertQueued(CommunityOwnershipTransferred::class, fn (CommunityOwnershipTransferred $mail) => $mail->hasTo($admin->email)
        && $mail->formerOwner->is($owner)
        && $mail->community->is($community));
    Mail::assertQueuedCount(1);
    Event::assertDispatched(CommunityMembersChanged::class, fn (CommunityMembersChanged $event) => $event->communityCuid === $community->cuid);
});

test('after the transfer the new owner has the rights of the owner and the former owner those of an admin (#1339)', function () {
    $community = $this->c['community'];
    $this->actingAs($this->c['owner'])->postJson(ownershipPath($community), ownershipBody($this->c['admin']))->assertOk();
    $communityPath = ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);

    $this->app['auth']->forgetGuards();
    $this->actingAs(reloadedUser($this->c['owner']))->patchJson($communityPath, ['description' => 'Still mine?'])->assertForbidden();
    $this->actingAs(reloadedUser($this->c['owner']))->deleteJson($communityPath)->assertForbidden();
    // An admin can leave (#1338); the owner could not
    $this->actingAs(reloadedUser($this->c['owner']))
        ->postJson(ownershipPath($community), ownershipBody($this->c['moderator']))
        ->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->actingAs(reloadedUser($this->c['admin']))->patchJson($communityPath, ['description' => 'Mine now'])->assertOk();
    // And the new owner can give it back
    $this->actingAs(reloadedUser($this->c['admin']))
        ->postJson(ownershipPath($community), ownershipBody($this->c['owner']))
        ->assertOk();
    expect($community->fresh()->owner_id)->toBe($this->c['owner']->id);
});

test('the new owner keeps their access groups and the name of the community stays (#1339)', function () {
    $community = $this->c['community'];
    $group = AccessGroup::query()->create(['community_id' => $community->id, 'name' => 'Paid members']);
    $group->members()->attach($this->c['admin']);
    $name = $community->name;
    $slug = $community->slug;

    $this->actingAs($this->c['owner'])->postJson(ownershipPath($community), ownershipBody($this->c['admin']))->assertOk();

    expect($group->members()->count())->toBe(1);
    expect($community->fresh()->name)->toBe($name);
    expect($community->fresh()->slug)->toBe($slug);
});

test('only an admin can receive the community (#1339)', function (string $who) {
    $community = $this->c['community'];

    $this->actingAs($this->c['owner'])
        ->postJson(ownershipPath($community), ownershipBody($this->c[$who]))
        ->assertStatus(422)
        ->assertJsonPath('type', KnowiiApiResponseType::BusinessIssue->value);

    expect($community->fresh()->owner_id)->toBe($this->c['owner']->id);
    Mail::assertNothingQueued();
    Event::assertNotDispatched(CommunityMembersChanged::class);
})->with(['moderator', 'member', 'owner']);

test('a user who is not a member cannot receive the community: 404 (#1339)', function () {
    $community = $this->c['community'];

    $this->actingAs($this->c['owner'])->postJson(ownershipPath($community), ownershipBody($this->c['outsider']))->assertNotFound();
    $this->actingAs($this->c['owner'])->postJson(ownershipPath($community), ['member' => 'unknowncuid', 'password' => 'password'])->assertNotFound();

    expect($community->fresh()->owner_id)->toBe($this->c['owner']->id);
});

test('the transfer needs the password of the owner (#1339)', function () {
    $community = $this->c['community'];

    $this->actingAs($this->c['owner'])
        ->postJson(ownershipPath($community), ownershipBody($this->c['admin'], 'not-the-password'))
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value)
        ->assertJsonStructure(['errors' => ['password']]);

    $this->actingAs($this->c['owner'])
        ->postJson(ownershipPath($community), ['member' => $this->c['admin']->cuid])
        ->assertStatus(400)
        ->assertJsonStructure(['errors' => ['password']]);

    $this->actingAs($this->c['owner'])
        ->postJson(ownershipPath($community), ['password' => 'password'])
        ->assertStatus(400)
        ->assertJsonStructure(['errors' => ['member']]);

    expect($community->fresh()->owner_id)->toBe($this->c['owner']->id);
    Mail::assertNothingQueued();
});

test('only the owner transfers the ownership (#1339)', function (string $who) {
    $community = $this->c['community'];
    $other = User::factory()->withUserProfile()->create();
    $community->users()->attach($other, ['role' => KnowiiCommunityMemberRole::Admin]);

    $this->actingAs($this->c[$who])->postJson(ownershipPath($community), ownershipBody($other))->assertForbidden();

    expect($community->fresh()->owner_id)->toBe($this->c['owner']->id);
})->with(['admin', 'moderator', 'member', 'outsider']);

test('a personal community never changes its owner (#1339)', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $personal = $user->ownedCommunities()->where('visibility', KnowiiCommunityVisibility::Personal)->firstOrFail();

    $this->actingAs($user)->postJson(ownershipPath($personal), ownershipBody($this->c['admin']))->assertForbidden();

    expect($personal->fresh()->owner_id)->toBe($user->id);
});

test('the transfer needs a session or a token with communities:write, and a verified address (#1339)', function () {
    $community = $this->c['community'];
    $this->postJson(ownershipPath($community), ownershipBody($this->c['admin']))->assertUnauthorized();

    Sanctum::actingAs($this->c['owner'], ['communities:read']);
    $this->postJson(ownershipPath($community), ownershipBody($this->c['admin']))->assertForbidden();
    expect($community->fresh()->owner_id)->toBe($this->c['owner']->id);

    Sanctum::actingAs($this->c['owner'], ['communities:write']);
    $this->postJson(ownershipPath($community), ownershipBody($this->c['admin']))->assertOk();
    expect($community->fresh()->owner_id)->toBe($this->c['admin']->id);

    $this->app['auth']->forgetGuards();
    $newOwner = reloadedUser($this->c['admin']);
    $newOwner->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($newOwner)->postJson(ownershipPath($community), ownershipBody($this->c['owner']))->assertForbidden();
});

test('the transfer is rate limited: no unlimited password guessing (#1339)', function () {
    $community = $this->c['community'];
    $this->actingAs($this->c['owner']);

    for ($i = 0; $i < Constants::OWNERSHIP_TRANSFERS_PER_MINUTE; $i++) {
        $this->postJson(ownershipPath($community), ownershipBody($this->c['admin'], 'guess-'.$i))->assertStatus(400);
    }

    $this->postJson(ownershipPath($community), ownershipBody($this->c['admin']))->assertStatus(429);
    expect($community->fresh()->owner_id)->toBe($this->c['owner']->id);
});

test('the action checks the owner by itself (#1339, ADR 0006)', function () {
    $action = app(TransfersCommunityOwnership::class);

    expect(fn () => $action->transfer($this->c['admin'], $this->c['community'], ownershipBody($this->c['moderator'])))
        ->toThrow(AuthorizationException::class);
});

test('the email names the community and the former owner (#1339)', function () {
    $mail = new CommunityOwnershipTransferred($this->c['community'], $this->c['owner']);

    $mail->assertSeeInHtml($this->c['community']->name, false);
    $mail->assertSeeInHtml($this->c['owner']->name, false);
    $mail->assertSeeInHtml(route('communities.settings', ['communitySlug' => $this->c['community']->slug]), false);
    expect($mail->build()->subject)->toContain($this->c['community']->name);
});
