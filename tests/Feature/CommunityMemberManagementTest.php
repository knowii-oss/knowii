<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiModeratorPermission;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Mail\CommunityInvitation as CommunityInvitationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->c = communityWithMembers();
    Event::fake([CommunityMembersChanged::class]);
    Mail::fake();
});

function membersPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

function memberPath(Community $community, User $member): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBER, [ApiRoutes::PARAM_COMMUNITY => $community->cuid, ApiRoutes::PARAM_MEMBER => $member->cuid]);
}

function invitationsPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_INVITATIONS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

function invitationPath(Community $community, CommunityInvitation $invitation): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_INVITATION, [ApiRoutes::PARAM_COMMUNITY => $community->cuid, ApiRoutes::PARAM_INVITATION => $invitation->cuid]);
}

function roleOf(Community $community, User $user): ?KnowiiCommunityMemberRole
{
    return $community->getMemberById($user->id)?->role;
}

function giveModeratorsManageMembers(Community $community): void
{
    $community->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_members' => true]]);
}

function freshUser(User $user): User
{
    // actingAs() keeps the model in memory, with its cached communities: a fresh model sees the new roles
    return User::query()->findOrFail($user->id);
}

// ---------------------------------------------------------------------------------------------------------------------
// List
// ---------------------------------------------------------------------------------------------------------------------

test('members list the owner first, then admins, moderators and members, with their role (#1242)', function () {
    $this->actingAs($this->c['member'])
        ->getJson(membersPath($this->c['community']))
        ->assertOk()
        ->assertJsonPath('data.*.role', ['owner', 'admin', 'moderator', 'member'])
        ->assertJsonPath('data.0.cuid', $this->c['owner']->cuid)
        ->assertJsonPath('metadata.pagination.totalCount', 4)
        ->assertJsonMissingPath('data.0.email');
});

test('the members search keeps the mention autocomplete and adds the role (#1177, #1242)', function () {
    $this->c['member']->update(['username' => 'ada_lovelace', 'name' => 'Ada Lovelace']);

    $this->actingAs($this->c['owner'])
        ->getJson(membersPath($this->c['community']).'?query=love')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.username', 'ada_lovelace')
        ->assertJsonPath('data.0.role', 'member');
});

test('outsiders cannot list the members (#1242)', function () {
    $this->actingAs($this->c['outsider'])->getJson(membersPath($this->c['community']))->assertForbidden();
});

// ---------------------------------------------------------------------------------------------------------------------
// Role hierarchy: change roles
// ---------------------------------------------------------------------------------------------------------------------

test('the owner gives any role to admins, moderators and members (#1242)', function (string $target, string $role) {
    $this->actingAs($this->c['owner'])
        ->patchJson(memberPath($this->c['community'], $this->c[$target]), ['role' => $role])
        ->assertOk()
        ->assertJsonPath('data.role', $role)
        ->assertJsonPath('data.cuid', $this->c[$target]->cuid);

    expect(roleOf($this->c['community'], $this->c[$target]))->toBe(KnowiiCommunityMemberRole::from($role));
    Event::assertDispatched(CommunityMembersChanged::class);
})->with([
    ['member', 'admin'],
    ['member', 'moderator'],
    ['admin', 'member'],
    ['moderator', 'admin'],
]);

test('admins change moderators and members, below admin only (#1242)', function () {
    $community = $this->c['community'];

    $this->actingAs($this->c['admin'])->patchJson(memberPath($community, $this->c['member']), ['role' => 'moderator'])->assertOk();
    $this->actingAs($this->c['admin'])->patchJson(memberPath($community, $this->c['moderator']), ['role' => 'member'])->assertOk();

    expect(roleOf($community, $this->c['member']))->toBe(KnowiiCommunityMemberRole::Moderator)
        ->and(roleOf($community, $this->c['moderator']))->toBe(KnowiiCommunityMemberRole::Member);
});

test('admins never make admins, never act on admins or the owner (#1242)', function (string $target, string $role) {
    $before = roleOf($this->c['community'], $this->c[$target]);

    $this->actingAs($this->c['admin'])
        ->patchJson(memberPath($this->c['community'], $this->c[$target]), ['role' => $role])
        ->assertForbidden();

    expect(roleOf($this->c['community'], $this->c[$target]))->toBe($before);
})->with([
    ['member', 'admin'],
    ['owner', 'member'],
    ['admin', 'member'],
]);

test('moderators without the "manage members" right cannot change roles (#1242)', function () {
    $this->actingAs($this->c['moderator'])
        ->patchJson(memberPath($this->c['community'], $this->c['member']), ['role' => 'member'])
        ->assertForbidden();
});

test('moderators with the "manage members" right act on members only, and give the member role only (#1242)', function () {
    giveModeratorsManageMembers($this->c['community']);
    $community = $this->c['community'];
    $other = User::factory()->withUserProfile()->create();
    $community->users()->attach($other, ['role' => KnowiiCommunityMemberRole::Moderator]);

    $this->actingAs($this->c['moderator'])->patchJson(memberPath($community, $this->c['member']), ['role' => 'moderator'])->assertForbidden();
    $this->actingAs($this->c['moderator'])->patchJson(memberPath($community, $other), ['role' => 'member'])->assertForbidden();
    $this->actingAs($this->c['moderator'])->patchJson(memberPath($community, $this->c['member']), ['role' => 'member'])->assertOk();
});

test('members and outsiders cannot change roles (#1242)', function (string $actor) {
    $this->actingAs($this->c[$actor])
        ->patchJson(memberPath($this->c['community'], $this->c['moderator']), ['role' => 'member'])
        ->assertForbidden();
})->with(['member', 'outsider']);

test('nobody changes their own role (#1242)', function () {
    $this->actingAs($this->c['admin'])
        ->patchJson(memberPath($this->c['community'], $this->c['admin']), ['role' => 'member'])
        ->assertForbidden();
});

test('the role must be admin, moderator or member (#1242)', function () {
    $this->actingAs($this->c['owner'])
        ->patchJson(memberPath($this->c['community'], $this->c['member']), ['role' => 'owner'])
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);
});

test('a user who is not a member gives a 404 (#1242)', function () {
    $this->actingAs($this->c['owner'])
        ->patchJson(memberPath($this->c['community'], $this->c['outsider']), ['role' => 'member'])
        ->assertNotFound();

    $this->actingAs($this->c['owner'])->deleteJson(memberPath($this->c['community'], $this->c['outsider']))->assertNotFound();
});

test('a member of another community gives a 404 (#1242)', function () {
    $other = communityWithMembers();

    $this->actingAs($this->c['owner'])
        ->patchJson(memberPath($this->c['community'], $other['member']), ['role' => 'admin'])
        ->assertNotFound();

    expect(roleOf($other['community'], $other['member']))->toBe(KnowiiCommunityMemberRole::Member);
});

// ---------------------------------------------------------------------------------------------------------------------
// Role hierarchy: remove
// ---------------------------------------------------------------------------------------------------------------------

test('the owner removes admins, moderators and members (#1242)', function (string $target) {
    $this->actingAs($this->c['owner'])->deleteJson(memberPath($this->c['community'], $this->c[$target]))->assertNoContent();

    expect(freshUser($this->c[$target])->belongsToCommunity($this->c['community']))->toBeFalse();
    Event::assertDispatched(CommunityMembersChanged::class);
})->with(['admin', 'moderator', 'member']);

test('admins remove moderators and members, never admins or the owner (#1242)', function () {
    $community = $this->c['community'];

    $this->actingAs($this->c['admin'])->deleteJson(memberPath($community, $this->c['owner']))->assertForbidden();
    $other = User::factory()->withUserProfile()->create();
    $community->users()->attach($other, ['role' => KnowiiCommunityMemberRole::Admin]);
    $this->actingAs($this->c['admin'])->deleteJson(memberPath($community, $other))->assertForbidden();

    $this->actingAs($this->c['admin'])->deleteJson(memberPath($community, $this->c['moderator']))->assertNoContent();
    $this->actingAs($this->c['admin'])->deleteJson(memberPath($community, $this->c['member']))->assertNoContent();
});

test('moderators remove members only, with the "manage members" right (#1242)', function () {
    $community = $this->c['community'];

    $this->actingAs($this->c['moderator'])->deleteJson(memberPath($community, $this->c['member']))->assertForbidden();

    giveModeratorsManageMembers($community);
    $this->actingAs($this->c['moderator'])->deleteJson(memberPath($community, $this->c['admin']))->assertForbidden();
    $this->actingAs($this->c['moderator'])->deleteJson(memberPath($community, $this->c['member']))->assertNoContent();
});

// ---------------------------------------------------------------------------------------------------------------------
// Invitations
// ---------------------------------------------------------------------------------------------------------------------

test('an invitation stores the address and the role and queues the email (#1242)', function () {
    $this->actingAs($this->c['admin'])
        ->postJson(invitationsPath($this->c['community']), ['email' => 'Grace@Example.com', 'role' => 'moderator'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'grace@example.com')
        ->assertJsonPath('data.role', 'moderator')
        ->assertJsonPath('data.is_expired', false);

    Mail::assertQueued(CommunityInvitationMail::class, fn (CommunityInvitationMail $mail) => $mail->hasTo('grace@example.com'));
    Event::assertDispatched(CommunityMembersChanged::class);
});

test('inviting the same address again replaces the invitation (#1242)', function () {
    $community = $this->c['community'];
    $this->actingAs($this->c['owner'])->postJson(invitationsPath($community), ['email' => 'grace@example.com', 'role' => 'member'])->assertCreated();
    $this->actingAs($this->c['owner'])->postJson(invitationsPath($community), ['email' => 'GRACE@example.com', 'role' => 'admin'])->assertCreated();

    expect($community->communityInvitations()->count())->toBe(1)
        ->and($community->communityInvitations()->first()?->role)->toBe(KnowiiCommunityMemberRole::Admin);
    Mail::assertQueuedCount(2);
});

test('invitations follow the role hierarchy (#1242)', function () {
    $community = $this->c['community'];

    $this->actingAs($this->c['admin'])->postJson(invitationsPath($community), ['email' => 'a@example.com', 'role' => 'admin'])->assertForbidden();
    $this->actingAs($this->c['moderator'])->postJson(invitationsPath($community), ['email' => 'b@example.com', 'role' => 'member'])->assertForbidden();

    giveModeratorsManageMembers($community);
    $this->actingAs($this->c['moderator'])->postJson(invitationsPath($community), ['email' => 'c@example.com', 'role' => 'moderator'])->assertForbidden();
    $this->actingAs($this->c['moderator'])->postJson(invitationsPath($community), ['email' => 'd@example.com', 'role' => 'member'])->assertCreated();
    $this->actingAs($this->c['member'])->postJson(invitationsPath($community), ['email' => 'e@example.com', 'role' => 'member'])->assertForbidden();

    expect($community->communityInvitations()->pluck('email')->all())->toBe(['d@example.com']);
});

test('members cannot be invited again, and the address must be valid (#1242)', function () {
    $community = $this->c['community'];

    $this->actingAs($this->c['owner'])
        ->postJson(invitationsPath($community), ['email' => strtoupper($this->c['member']->email), 'role' => 'member'])
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);

    $this->actingAs($this->c['owner'])
        ->postJson(invitationsPath($community), ['email' => 'not an email', 'role' => 'member'])
        ->assertStatus(400);

    Mail::assertNothingQueued();
});

test('member managers list and cancel invitations; others cannot (#1242)', function () {
    $community = $this->c['community'];
    $invitation = $community->communityInvitations()->create(['email' => 'grace@example.com', 'role' => KnowiiCommunityMemberRole::Moderator]);

    $this->actingAs($this->c['member'])->getJson(invitationsPath($community))->assertForbidden();
    $this->actingAs($this->c['admin'])->getJson(invitationsPath($community))->assertOk()->assertJsonPath('data.0.email', 'grace@example.com');

    giveModeratorsManageMembers($community);
    $this->actingAs($this->c['moderator'])->deleteJson(invitationPath($community, $invitation))->assertForbidden();
    $this->actingAs($this->c['admin'])->deleteJson(invitationPath($community, $invitation))->assertNoContent();

    expect($community->communityInvitations()->count())->toBe(0);
});

test('an invitation of another community gives a 404 (#1242)', function () {
    $other = communityWithMembers();
    $invitation = $other['community']->communityInvitations()->create(['email' => 'grace@example.com', 'role' => KnowiiCommunityMemberRole::Member]);

    $this->actingAs($this->c['owner'])->deleteJson(invitationPath($this->c['community'], $invitation))->assertNotFound();

    expect($invitation->fresh())->not->toBeNull();
});

// ---------------------------------------------------------------------------------------------------------------------
// Accept
// ---------------------------------------------------------------------------------------------------------------------

function acceptUrl(CommunityInvitation $invitation): string
{
    return URL::temporarySignedRoute('community-invitations.accept', $invitation->expiresAt(), ['invitation' => $invitation]);
}

test('the invited user joins with the role of the invitation, and the invitation goes away (#1242)', function () {
    $community = $this->c['community'];
    $grace = User::factory()->withUserProfile()->create(['email' => 'grace@example.com']);
    $invitation = $community->communityInvitations()->create(['email' => 'GRACE@example.com', 'role' => KnowiiCommunityMemberRole::Moderator]);

    $this->actingAs($grace)->get(acceptUrl($invitation))
        ->assertRedirect(route('communities.show', ['communitySlug' => $community->slug]))
        ->assertSessionHas('flash.banner', 'Welcome to '.$community->name);

    expect(roleOf($community, $grace))->toBe(KnowiiCommunityMemberRole::Moderator)
        ->and($invitation->fresh())->toBeNull();
    Event::assertDispatched(CommunityMembersChanged::class);
});

test('another account cannot use the link of an invitation (#1242)', function () {
    $community = $this->c['community'];
    $invitation = $community->communityInvitations()->create(['email' => 'grace@example.com', 'role' => KnowiiCommunityMemberRole::Admin]);

    $this->actingAs($this->c['outsider'])->get(acceptUrl($invitation))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('flash.bannerStyle', 'danger');

    expect(freshUser($this->c['outsider'])->belongsToCommunity($community))->toBeFalse()
        ->and($invitation->fresh())->not->toBeNull();
});

test('an expired invitation does not work (#1242)', function () {
    $community = $this->c['community'];
    $grace = User::factory()->withUserProfile()->create(['email' => 'grace@example.com']);
    $invitation = $community->communityInvitations()->create(['email' => 'grace@example.com', 'role' => KnowiiCommunityMemberRole::Member]);
    $url = acceptUrl($invitation);

    $this->travel(CommunityInvitation::VALIDITY_DAYS + 1)->days();

    // The signature expired with the invitation
    $this->actingAs($grace)->get($url)->assertForbidden();
    expect(freshUser($grace)->belongsToCommunity($community))->toBeFalse();
});

test('an unsigned or altered link does not work (#1242)', function () {
    $community = $this->c['community'];
    $grace = User::factory()->withUserProfile()->create(['email' => 'grace@example.com']);
    $invitation = $community->communityInvitations()->create(['email' => 'grace@example.com', 'role' => KnowiiCommunityMemberRole::Member]);

    $this->actingAs($grace)->get(route('community-invitations.accept', ['invitation' => $invitation]))->assertForbidden();
    $this->actingAs($grace)->get(acceptUrl($invitation).'x')->assertForbidden();
});

test('guests sign in before they accept (#1242)', function () {
    $invitation = $this->c['community']->communityInvitations()->create(['email' => 'grace@example.com', 'role' => KnowiiCommunityMemberRole::Member]);

    $this->get(acceptUrl($invitation))->assertRedirect(route('login'));
});

test('the invitation email links to the signed accept route (#1242)', function () {
    $invitation = $this->c['community']->communityInvitations()->create(['email' => 'grace@example.com', 'role' => KnowiiCommunityMemberRole::Member]);

    $html = (new CommunityInvitationMail($invitation))->render();

    expect($html)->toContain($this->c['community']->name)
        ->and($html)->toContain('/community-invitations/'.$invitation->cuid)
        ->and($html)->toContain('signature=');
});

// ---------------------------------------------------------------------------------------------------------------------
// Settings page
// ---------------------------------------------------------------------------------------------------------------------

test('the settings page opens for moderators with the "manage members" right, with their role (#1242)', function () {
    $url = route('communities.settings', ['communitySlug' => $this->c['community']->slug]);

    $this->actingAs($this->c['moderator'])->get($url)->assertForbidden();

    giveModeratorsManageMembers($this->c['community']);
    $this->actingAs($this->c['moderator'])->get($url)->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentRole', 'moderator')
            ->where('permissions.canAddCommunityMembers', true)
            ->where('permissions.canManageSpaces', false));

    $this->actingAs($this->c['owner'])->get($url)->assertOk()->assertInertia(fn ($page) => $page->where('currentRole', 'owner'));
});
