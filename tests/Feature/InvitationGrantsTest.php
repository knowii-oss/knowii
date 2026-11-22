<?php

use App\ApiRoutes;
use App\Contracts\CommunityMembers\AcceptsCommunityInvitations;
use App\Contracts\CommunityMembers\InvitesCommunityMembers;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiModeratorPermission;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Mail\CommunityInvitation as CommunityInvitationMail;
use App\Models\AccessGroup;
use App\Models\AccessGroupRule;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityJoinBlock;
use App\Models\MemberTag;
use App\Models\User;
use App\Support\InvitationGrants;
use App\Support\MemberTagAssignments;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

/*
 * An invitation carries access groups and member tags (#1390 part A): the create, the rights and the token
 * abilities, the list, the join, and the table.
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    // A fixed name and slug: the assertions read them
    $this->community->forceFill(['name' => 'Qxcommunity Example', 'slug' => 'qxcommunity-example'])->save();
    $this->paid = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Qxgrant Paid']);
    $this->alumni = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'alumni']);
    $this->founder = MemberTag::factory()->forMembers()->create(['community_id' => $this->community->id, 'name' => 'Qxgrant Founder', 'color' => 'purple']);
    $this->onboarded = MemberTag::factory()->forManagers()->create(['community_id' => $this->community->id, 'name' => 'Qxgrant Onboarded']);
    Event::fake([CommunityMembersChanged::class, AccessGroupsChanged::class, SpaceTreeChanged::class]);
    Mail::fake();
});

function grantsInvitationsPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_INVITATIONS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

function grantsAcceptUrl(CommunityInvitation $invitation): string
{
    return URL::temporarySignedRoute('community-invitations.accept', $invitation->expiresAt(), ['invitation' => $invitation]);
}

function grantsModeratorsManageMembers(Community $community): void
{
    $community->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_members' => true]]);
}

/**
 * An invitation with links, as the action writes them
 *
 * @param  list<AccessGroup>  $groups
 * @param  list<MemberTag>  $tags
 */
function grantsInvitation(Community $community, string $email, array $groups, array $tags, KnowiiCommunityMemberRole $role = KnowiiCommunityMemberRole::Member): CommunityInvitation
{
    /** @var CommunityInvitation $invitation */
    $invitation = $community->communityInvitations()->create(['email' => $email, 'role' => $role]);
    InvitationGrants::attach($invitation, $groups, $tags);

    return $invitation;
}

// ---------------------------------------------------------------------------------------------------------------------
// The create
// ---------------------------------------------------------------------------------------------------------------------

test('the owner, an admin and a moderator who manages members invite with groups and tags; the answer lists both by name (#1390)', function (string $who) {
    grantsModeratorsManageMembers($this->community);
    $email = 'qx-'.$who.'@example.com';

    $this->actingAs($this->c[$who])
        ->postJson(grantsInvitationsPath($this->community), [
            'email' => $email,
            'role' => 'member',
            'access_groups' => [$this->paid->cuid, $this->alumni->cuid],
            'member_tags' => [$this->onboarded->cuid, $this->founder->cuid],
        ])
        ->assertCreated()
        ->assertJsonPath('data.access_groups', [['cuid' => $this->alumni->cuid, 'name' => 'alumni'], ['cuid' => $this->paid->cuid, 'name' => 'Qxgrant Paid']])
        ->assertJsonPath('data.member_tags.0.cuid', $this->founder->cuid)
        ->assertJsonPath('data.member_tags.0.name', 'Qxgrant Founder')
        ->assertJsonPath('data.member_tags.0.color', 'purple')
        ->assertJsonPath('data.member_tags.0.visibility', 'members')
        ->assertJsonPath('data.member_tags.1.visibility', 'managers');

    $invitation = CommunityInvitation::query()->where('email', $email)->firstOrFail();
    expect($invitation->accessGroups()->pluck('access_groups.id')->sort()->values()->all())->toBe(collect([$this->paid->id, $this->alumni->id])->sort()->values()->all())
        ->and($invitation->memberTags()->count())->toBe(2);
})->with(['owner', 'admin', 'moderator']);

test('a cuid of another community or of nothing is an error on its field, and nothing is created (#1390)', function () {
    $foreignGroup = AccessGroup::query()->create(['community_id' => Community::factory()->create()->id, 'name' => 'Elsewhere']);
    $foreignTag = MemberTag::factory()->create(['name' => 'Elsewhere']);
    $owner = $this->c['owner'];

    $this->actingAs($owner)->postJson(grantsInvitationsPath($this->community), ['email' => 'qx1@example.com', 'role' => 'member', 'access_groups' => [$foreignGroup->cuid]])
        ->assertStatus(400)->assertJsonPath('errors.access_groups.0', 'No access group of the community matches');
    $this->actingAs($owner)->postJson(grantsInvitationsPath($this->community), ['email' => 'qx1@example.com', 'role' => 'member', 'access_groups' => ['nothing']])
        ->assertStatus(400)->assertJsonPath('errors.access_groups.0', 'No access group of the community matches');
    $this->actingAs($owner)->postJson(grantsInvitationsPath($this->community), ['email' => 'qx1@example.com', 'role' => 'member', 'member_tags' => [$foreignTag->cuid]])
        ->assertStatus(400)->assertJsonPath('errors.member_tags.0', 'No member tag of the community matches');
    $this->actingAs($owner)->postJson(grantsInvitationsPath($this->community), ['email' => 'qx1@example.com', 'role' => 'member', 'member_tags' => ['nothing']])
        ->assertStatus(400)->assertJsonPath('errors.member_tags.0', 'No member tag of the community matches');

    expect($this->community->communityInvitations()->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('an invitation holds at most 20 tags, and a duplicate cuid counts once (#1390)', function () {
    $tags = MemberTag::factory()->count(21)->sequence(fn ($sequence) => ['name' => 'Qxmany '.$sequence->index])->create(['community_id' => $this->community->id]);
    $cuids = $tags->pluck('cuid')->all();

    $this->actingAs($this->c['owner'])->postJson(grantsInvitationsPath($this->community), ['email' => 'qx1@example.com', 'role' => 'member', 'member_tags' => $cuids])
        ->assertStatus(400)->assertJsonPath('errors.member_tags.0', 'An invitation holds at most 20 tags');

    $twenty = array_slice($cuids, 0, 20);
    $this->actingAs($this->c['owner'])
        ->postJson(grantsInvitationsPath($this->community), ['email' => 'qx2@example.com', 'role' => 'member', 'member_tags' => [...$twenty, $twenty[0]], 'access_groups' => [$this->paid->cuid, $this->paid->cuid]])
        ->assertCreated()
        ->assertJsonCount(20, 'data.member_tags')
        ->assertJsonCount(1, 'data.access_groups');
});

test('a list holds at most twice its limit as it comes in, and its items are compared as strings (#1390)', function () {
    $path = grantsInvitationsPath($this->community);

    $this->actingAs($this->c['owner'])->postJson($path, ['email' => 'qx1@example.com', 'role' => 'member', 'access_groups' => array_fill(0, 101, $this->paid->cuid)])
        ->assertStatus(400)->assertJsonPath('errors.access_groups.0', 'An invitation holds at most 50 access groups');
    $this->actingAs($this->c['owner'])->postJson($path, ['email' => 'qx1@example.com', 'role' => 'member', 'member_tags' => array_fill(0, 41, $this->founder->cuid)])
        ->assertStatus(400)->assertJsonPath('errors.member_tags.0', 'An invitation holds at most 20 tags');
    // Twice the limit, all the same: one group, one tag
    $this->actingAs($this->c['owner'])
        ->postJson($path, ['email' => 'qx2@example.com', 'role' => 'member', 'access_groups' => array_fill(0, 100, $this->paid->cuid), 'member_tags' => array_fill(0, 40, $this->founder->cuid)])
        ->assertCreated()
        ->assertJsonCount(1, 'data.access_groups')
        ->assertJsonCount(1, 'data.member_tags');
    // A cuid that looks like a number is a string like any other
    $this->actingAs($this->c['owner'])->postJson($path, ['email' => 'qx3@example.com', 'role' => 'member', 'access_groups' => ['123', '0123']])
        ->assertStatus(400)->assertJsonPath('errors.access_groups.0', 'No access group of the community matches');
});

test('a group or a tag deleted while the invitation is made is left out, and the invitation is made (#1390)', function () {
    // The group and the tag go just after the read of the lists, before the transaction of the create
    $gone = [];
    AccessGroup::retrieved(function (AccessGroup $group) use (&$gone) {
        if (! isset($gone['group']) && $group->id === $this->paid->id) {
            $gone['group'] = DB::table('access_groups')->where('id', $group->id)->delete();
        }
    });
    MemberTag::retrieved(function (MemberTag $tag) use (&$gone) {
        if (! isset($gone['tag']) && $tag->id === $this->founder->id) {
            $gone['tag'] = DB::table('member_tags')->where('id', $tag->id)->delete();
        }
    });

    $this->actingAs($this->c['owner'])
        ->postJson(grantsInvitationsPath($this->community), [
            'email' => 'qx@example.com',
            'role' => 'member',
            'access_groups' => [$this->paid->cuid, $this->alumni->cuid],
            'member_tags' => [$this->founder->cuid, $this->onboarded->cuid],
        ])
        ->assertCreated()
        ->assertJsonPath('data.access_groups.*.cuid', [$this->alumni->cuid])
        ->assertJsonPath('data.member_tags.*.cuid', [$this->onboarded->cuid]);

    expect($gone)->toBe(['group' => 1, 'tag' => 1])
        ->and(DB::table('community_invitation_access_groups')->pluck('access_group_id')->all())->toBe([$this->alumni->id])
        ->and(DB::table('community_invitation_member_tags')->pluck('member_tag_id')->all())->toBe([$this->onboarded->id]);
    Mail::assertQueued(CommunityInvitationMail::class, 1);
});

test('a manager replaces an invitation only when they may give its role (#1390)', function () {
    grantsModeratorsManageMembers($this->community);
    $path = grantsInvitationsPath($this->community);
    $this->actingAs($this->c['owner'])->postJson($path, ['email' => 'qx@example.com', 'role' => 'admin', 'access_groups' => [$this->paid->cuid]])->assertCreated();
    $invitation = $this->community->communityInvitations()->sole();
    Mail::fake();

    $this->actingAs($this->c['moderator'])->postJson($path, ['email' => 'QX@example.com', 'role' => 'member'])
        ->assertForbidden()->assertJsonPath('message', 'This address has an invitation with a role that you cannot give');

    $kept = $this->community->communityInvitations()->sole();
    expect($kept->id)->toBe($invitation->id)
        ->and($kept->role)->toBe(KnowiiCommunityMemberRole::Admin)
        ->and($kept->accessGroups()->pluck('access_groups.id')->all())->toBe([$this->paid->id]);
    Mail::assertNothingQueued();

    // The owner gives every role: the owner replaces it
    $this->actingAs($this->c['owner'])->postJson($path, ['email' => 'qx@example.com', 'role' => 'member'])->assertCreated();
    expect($this->community->communityInvitations()->sole()->role)->toBe(KnowiiCommunityMemberRole::Member);
});

test('an invitation that expired gives nothing, and any manager replaces it (#1390)', function () {
    grantsModeratorsManageMembers($this->community);
    $this->travelTo(now()->setDate(2026, 11, 1)->setTime(10, 0));
    $invitation = grantsInvitation($this->community, 'qx-grace@example.com', [$this->paid], [$this->founder], KnowiiCommunityMemberRole::Admin);
    $this->travelTo(now()->setDate(2026, 11, 9)->setTime(10, 0));
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);

    expect(fn () => app(AcceptsCommunityInvitations::class)->accept($grace, $invitation))->toThrow(BusinessException::class, 'This invitation expired. Ask for a new one');
    expect(User::query()->findOrFail($grace->id)->belongsToCommunity($this->community))->toBeFalse()
        ->and(DB::table('access_group_members')->where('user_id', $grace->id)->exists())->toBeFalse()
        ->and(DB::table('member_tag_members')->where('user_id', $grace->id)->exists())->toBeFalse()
        ->and($invitation->accessGroups()->count())->toBe(1)
        ->and($invitation->memberTags()->count())->toBe(1)
        ->and(CommunityInvitation::query()->whereKey($invitation->id)->exists())->toBeTrue();

    $this->actingAs($this->c['moderator'])->postJson(grantsInvitationsPath($this->community), ['email' => 'qx-grace@example.com', 'role' => 'member'])->assertCreated();
    expect($this->community->communityInvitations()->sole()->role)->toBe(KnowiiCommunityMemberRole::Member)
        ->and(DB::table('community_invitation_access_groups')->count())->toBe(0);
});

test('groups need the right to manage the members of groups, tags the right to assign tags (#1390)', function () {
    // A member manager without those two rights: only a change of the policy makes one, so the test asks the gate
    Gate::before(static fn (User $user, string $ability) => in_array($ability, ['manageAccessGroupMembers', 'assignMemberTags'], true) ? false : null);

    $this->actingAs($this->c['admin'])->postJson(grantsInvitationsPath($this->community), ['email' => 'qx1@example.com', 'role' => 'member', 'access_groups' => [$this->paid->cuid]])
        ->assertForbidden()->assertJsonPath('message', 'You cannot give access groups');
    $this->actingAs($this->c['admin'])->postJson(grantsInvitationsPath($this->community), ['email' => 'qx1@example.com', 'role' => 'member', 'member_tags' => [$this->founder->cuid]])
        ->assertForbidden()->assertJsonPath('message', 'You cannot give member tags');
    $this->actingAs($this->c['admin'])->postJson(grantsInvitationsPath($this->community), ['email' => 'qx1@example.com', 'role' => 'member', 'access_groups' => [], 'member_tags' => []])
        ->assertCreated();
});

test('a token needs groups:write to give groups and member-tags:write to give tags; an empty list needs neither (#1390)', function () {
    $owner = $this->c['owner'];
    // The guard keeps the user of the last request: each request reads its own token
    $token = function (array $abilities) use ($owner): string {
        app('auth')->forgetGuards();

        return $owner->createToken('qx', $abilities)->plainTextToken;
    };
    $path = grantsInvitationsPath($this->community);

    $this->withToken($token(['communities:write']))->postJson($path, ['email' => 'qx1@example.com', 'role' => 'member', 'access_groups' => [$this->paid->cuid]])
        ->assertForbidden()->assertJsonPath('message', 'You cannot give access groups');
    $this->withToken($token(['communities:write']))->postJson($path, ['email' => 'qx1@example.com', 'role' => 'member', 'member_tags' => [$this->founder->cuid]])
        ->assertForbidden()->assertJsonPath('message', 'You cannot give member tags');
    // groups:write does not give tags, member-tags:write does not give groups
    $this->withToken($token(['communities:write', 'groups:write']))->postJson($path, ['email' => 'qx1@example.com', 'role' => 'member', 'member_tags' => [$this->founder->cuid]])
        ->assertForbidden();
    $this->withToken($token(['communities:write', 'member-tags:write']))->postJson($path, ['email' => 'qx1@example.com', 'role' => 'member', 'access_groups' => [$this->paid->cuid]])
        ->assertForbidden();
    expect($this->community->communityInvitations()->count())->toBe(0);

    $this->withToken($token(['communities:write', 'groups:write']))->postJson($path, ['email' => 'qx2@example.com', 'role' => 'member', 'access_groups' => [$this->paid->cuid]])
        ->assertCreated();
    $this->withToken($token(['communities:write', 'member-tags:write']))->postJson($path, ['email' => 'qx3@example.com', 'role' => 'member', 'member_tags' => [$this->founder->cuid]])
        ->assertCreated();
    $this->withToken($token(['communities:write']))->postJson($path, ['email' => 'qx4@example.com', 'role' => 'member', 'access_groups' => [], 'member_tags' => []])
        ->assertCreated();
});

test('the refusals come in order: the fields, the role, the member, then the groups and the tags (#1390)', function () {
    $member = User::factory()->withUserProfile()->create(['email' => 'qx-member@example.com']);
    $this->community->users()->attach($member, ['role' => KnowiiCommunityMemberRole::Member]);
    $nothing = ['access_groups' => ['nothing'], 'member_tags' => ['nothing']];
    $path = grantsInvitationsPath($this->community);

    $this->actingAs($this->c['admin'])->postJson($path, ['email' => 'not an email', 'role' => 'member', ...$nothing])
        ->assertStatus(400)->assertJsonPath('errors.email.0', fn ($message) => is_string($message))->assertJsonMissingPath('errors.access_groups');
    $this->actingAs($this->c['admin'])->postJson($path, ['email' => 'qx1@example.com', 'role' => 'admin', ...$nothing])->assertForbidden();
    $this->actingAs($this->c['admin'])->postJson($path, ['email' => 'QX-member@example.com', 'role' => 'member', ...$nothing])
        ->assertStatus(400)->assertJsonPath('errors.email.0', fn ($message) => is_string($message))->assertJsonMissingPath('errors.access_groups');
    $this->actingAs($this->c['admin'])->postJson($path, ['email' => 'qx1@example.com', 'role' => 'member', ...$nothing])
        ->assertStatus(400)->assertJsonPath('errors.access_groups.0', 'No access group of the community matches');
});

test('a new invitation of the same address has the lists of the new request only (#1390)', function () {
    $path = grantsInvitationsPath($this->community);
    $this->actingAs($this->c['owner'])->postJson($path, ['email' => 'qx@example.com', 'role' => 'member', 'access_groups' => [$this->paid->cuid], 'member_tags' => [$this->founder->cuid]])->assertCreated();
    $this->actingAs($this->c['owner'])->postJson($path, ['email' => 'QX@example.com', 'role' => 'member', 'member_tags' => [$this->onboarded->cuid]])->assertCreated();

    $invitation = $this->community->communityInvitations()->sole();
    expect($invitation->accessGroups()->count())->toBe(0)
        ->and($invitation->memberTags()->pluck('member_tags.id')->all())->toBe([$this->onboarded->id])
        ->and(DB::table('community_invitation_access_groups')->count())->toBe(0)
        ->and(DB::table('community_invitation_member_tags')->count())->toBe(1);
});

test('the log line of a create holds the counts of the groups and the tags, never their names (#1390)', function () {
    Log::spy();

    $this->actingAs($this->c['owner'])->postJson(grantsInvitationsPath($this->community), [
        'email' => 'qx@example.com', 'role' => 'member', 'access_groups' => [$this->paid->cuid], 'member_tags' => [$this->founder->cuid, $this->onboarded->cuid],
    ])->assertCreated();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []) => $message === 'Community invitation sent'
        && $context['access_groups'] === 1 && $context['member_tags'] === 2
        && ! str_contains((string) json_encode($context), 'Qxgrant') && ! str_contains((string) json_encode($context), 'qx@example.com'))->once();
});

test('a personal community invites nobody, and its invitation does not work (#1390)', function () {
    $owner = User::factory()->withUserProfile()->create(['email' => 'qx-owner@example.com']);
    $personal = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => KnowiiCommunityVisibility::Personal]);

    $this->actingAs($owner)->postJson(grantsInvitationsPath($personal), ['email' => 'qx@example.com', 'role' => 'member'])
        ->assertStatus(422)->assertJsonPath('message', 'A personal community cannot invite anyone');
    expect($personal->communityInvitations()->count())->toBe(0);
    Mail::assertNothingQueued();

    // An invitation that is in the table already
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx@example.com']);
    $invitation = $personal->communityInvitations()->create(['email' => 'qx@example.com', 'role' => KnowiiCommunityMemberRole::Admin]);
    $this->actingAs($grace)->get(grantsAcceptUrl($invitation))->assertRedirect(route('dashboard'))->assertSessionHas('flash.bannerStyle', 'danger');

    expect(User::query()->findOrFail($grace->id)->belongsToCommunity($personal))->toBeFalse();
});

// ---------------------------------------------------------------------------------------------------------------------
// The list
// ---------------------------------------------------------------------------------------------------------------------

test('the list is a page, newest first, with the pagination of the guide (#1390)', function () {
    foreach (range(1, 12) as $index) {
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(10, $index));
        $this->community->communityInvitations()->create(['email' => 'qx'.$index.'@example.com', 'role' => KnowiiCommunityMemberRole::Member]);
    }
    $this->travelBack();

    $this->actingAs($this->c['admin'])->getJson(grantsInvitationsPath($this->community))
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('data.0.email', 'qx12@example.com')
        ->assertJsonPath('data.9.email', 'qx3@example.com')
        ->assertJsonPath('metadata.pagination.totalCount', 12)
        ->assertJsonPath('metadata.pagination.nextOffset', 10);

    $this->actingAs($this->c['admin'])->getJson(grantsInvitationsPath($this->community).'?limit=20&offset=10')
        ->assertOk()
        ->assertJsonPath('data.*.email', ['qx2@example.com', 'qx1@example.com'])
        ->assertJsonPath('metadata.pagination.nextOffset', null);

    $this->actingAs($this->c['admin'])->getJson(grantsInvitationsPath($this->community).'?limit=5000')
        ->assertOk()->assertJsonPath('metadata.pagination.limit', 1000);
});

test('the list reads the groups and the tags of a whole page with one query each: 3 and 30 invitations, the same count (#1390)', function () {
    $count = function (int $invitations): int {
        $this->community->communityInvitations()->delete();
        foreach (range(1, $invitations) as $index) {
            grantsInvitation($this->community, 'qx'.$index.'@example.com', [$this->paid, $this->alumni], [$this->founder, $this->onboarded]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($this->c['admin'])->getJson(grantsInvitationsPath($this->community).'?limit=100')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        expect($response->json('data'))->toHaveCount($invitations)
            ->and($response->json('data.0.access_groups.0.name'))->toBe('alumni')
            ->and($response->json('data.0.member_tags.1.name'))->toBe('Qxgrant Onboarded');

        return $queries;
    };

    // The first request of a test reads what later requests find cached
    $count(3);
    $three = $count(3);
    $thirty = $count(30);

    expect($thirty)->toBe($three);
});

// ---------------------------------------------------------------------------------------------------------------------
// The join
// ---------------------------------------------------------------------------------------------------------------------

test('the join gives the groups by hand and the tags of the invitation (#1390)', function () {
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);
    $invitation = grantsInvitation($this->community, 'qx-grace@example.com', [$this->paid, $this->alumni], [$this->founder, $this->onboarded]);

    $this->actingAs($grace)->get(grantsAcceptUrl($invitation))->assertRedirect(route('communities.show', ['communitySlug' => 'qxcommunity-example']));

    $rows = DB::table('access_group_members')->where('user_id', $grace->id)->orderBy('access_group_id')->get(['access_group_id', 'manual', 'by_rule']);
    expect($rows->pluck('access_group_id')->all())->toBe([$this->paid->id, $this->alumni->id])
        ->and($rows->every(fn ($row) => $row->manual === true && $row->by_rule === false))->toBeTrue()
        ->and(DB::table('member_tag_members')->where('user_id', $grace->id)->pluck('member_tag_id')->sort()->values()->all())->toBe([$this->founder->id, $this->onboarded->id])
        ->and(CommunityInvitation::query()->whereKey($invitation->id)->exists())->toBeFalse()
        ->and(DB::table('community_invitation_access_groups')->count())->toBe(0)
        ->and(DB::table('community_invitation_member_tags')->count())->toBe(0);
});

test('a person who is a member already gets the groups and the tags, and keeps their role (#1390)', function () {
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);
    $this->community->users()->attach($grace, ['role' => KnowiiCommunityMemberRole::Moderator]);
    $invitation = grantsInvitation($this->community, 'qx-grace@example.com', [$this->paid], [$this->founder]);

    $this->actingAs($grace)->get(grantsAcceptUrl($invitation))->assertRedirect();

    expect($this->community->getMemberById($grace->id)?->role)->toBe(KnowiiCommunityMemberRole::Moderator)
        ->and($this->paid->members()->whereKey($grace->id)->exists())->toBeTrue()
        ->and($this->founder->members()->whereKey($grace->id)->exists())->toBeTrue();
});

test('the join locks the community `for key share` first, then the invitation `for update`, as a deletion of the community takes them (#1390)', function () {
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);
    $invitation = grantsInvitation($this->community, 'qx-grace@example.com', [$this->paid], [$this->founder]);
    $statements = [];
    DB::listen(static function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    app(AcceptsCommunityInvitations::class)->accept($grace, $invitation);

    $communityLock = array_key_first(array_filter($statements, static fn (string $sql): bool => str_contains($sql, 'from "communities"') && str_contains($sql, 'for key share')));
    $invitationLock = array_key_first(array_filter($statements, static fn (string $sql): bool => str_contains($sql, 'from "community_invitations"') && str_contains($sql, 'for update')));
    expect($communityLock)->toBeInt()
        ->and($invitationLock)->toBe($communityLock + 1)
        ->and($this->community->getMemberById($grace->id)?->role)->toBe(KnowiiCommunityMemberRole::Member);
});

test('a person whom a manager removed joins with an invitation: the grants are given and the block ends (#1390)', function () {
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);
    CommunityJoinBlock::query()->create(['community_id' => $this->community->id, 'user_id' => $grace->id]);
    $invitation = grantsInvitation($this->community, 'qx-grace@example.com', [$this->paid], [$this->founder]);

    $this->actingAs($grace)->get(grantsAcceptUrl($invitation))->assertRedirect(route('communities.show', ['communitySlug' => 'qxcommunity-example']));

    expect(User::query()->findOrFail($grace->id)->belongsToCommunity($this->community))->toBeTrue()
        ->and($this->paid->members()->whereKey($grace->id)->exists())->toBeTrue()
        ->and($this->founder->members()->whereKey($grace->id)->exists())->toBeTrue()
        ->and(CommunityJoinBlock::query()->where('community_id', $this->community->id)->where('user_id', $grace->id)->exists())->toBeFalse();
});

test('a group that an entitlement rule feeds goes on an invitation, and the join gives it by hand (#1390)', function () {
    AccessGroupRule::query()->create(['access_group_id' => $this->paid->id, 'entitlement_key' => 'example.paid']);

    $this->actingAs($this->c['owner'])
        ->postJson(grantsInvitationsPath($this->community), ['email' => 'qx-grace@example.com', 'role' => 'member', 'access_groups' => [$this->paid->cuid]])
        ->assertCreated()
        ->assertJsonPath('data.access_groups.0.cuid', $this->paid->cuid);
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);

    app(AcceptsCommunityInvitations::class)->accept($grace, $this->community->communityInvitations()->sole());

    $row = DB::table('access_group_members')->where('access_group_id', $this->paid->id)->where('user_id', $grace->id)->first(['manual', 'by_rule']);
    expect($row?->manual)->toBeTrue()
        ->and($row?->by_rule)->toBeFalse();
});

test('a member with 20 tags joins; the 21st tag is skipped with one log line without a name (#1390)', function () {
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);
    $this->community->users()->attach($grace, ['role' => KnowiiCommunityMemberRole::Member]);
    MemberTag::factory()->count(20)->sequence(fn ($sequence) => ['name' => 'Qxfull '.$sequence->index])->create(['community_id' => $this->community->id])
        ->each(fn (MemberTag $tag) => MemberTagAssignments::add($tag, $grace));
    $invitation = grantsInvitation($this->community, 'qx-grace@example.com', [$this->paid], [$this->founder]);
    Log::spy();

    $community = app(AcceptsCommunityInvitations::class)->accept($grace, $invitation);

    expect($community->id)->toBe($this->community->id)
        ->and($this->founder->members()->whereKey($grace->id)->exists())->toBeFalse()
        ->and($this->paid->members()->whereKey($grace->id)->exists())->toBeTrue()
        ->and(CommunityInvitation::query()->whereKey($invitation->id)->exists())->toBeFalse();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []) => $context === ['community' => $this->community->cuid, 'tag' => $this->founder->cuid, 'member' => $grace->cuid]
        && ! str_contains($message, 'Qxgrant'))->once();
});

test('a group or a tag deleted before the join leaves the invitation, and the join works (#1390)', function () {
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);
    $invitation = grantsInvitation($this->community, 'qx-grace@example.com', [$this->paid, $this->alumni], [$this->founder, $this->onboarded]);

    $this->paid->delete();
    $this->founder->delete();
    expect($invitation->accessGroups()->pluck('access_groups.id')->all())->toBe([$this->alumni->id])
        ->and($invitation->memberTags()->pluck('member_tags.id')->all())->toBe([$this->onboarded->id]);

    $this->actingAs($grace)->get(grantsAcceptUrl($invitation))->assertRedirect(route('communities.show', ['communitySlug' => 'qxcommunity-example']));

    expect($this->alumni->members()->whereKey($grace->id)->exists())->toBeTrue()
        ->and($this->onboarded->members()->whereKey($grace->id)->exists())->toBeTrue();
});

test('an invitation replaced with another role after the page read it: the person joins with the role of the new invitation only (#1390)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-18T10:00:00Z'));
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);
    grantsInvitation($this->community, 'qx-grace@example.com', [$this->paid], [$this->founder], KnowiiCommunityMemberRole::Admin);
    // The invitation as the page of the link read it, before the replace
    $read = CommunityInvitation::query()->where('email', 'qx-grace@example.com')->sole();
    app(InvitesCommunityMembers::class)->invite($this->c['owner'], $this->community, ['email' => 'qx-grace@example.com', 'role' => 'member']);

    expect(fn () => app(AcceptsCommunityInvitations::class)->accept($grace, $read))->toThrow(ModelNotFoundException::class);
    expect(User::query()->findOrFail($grace->id)->belongsToCommunity($this->community))->toBeFalse()
        ->and(DB::table('access_group_members')->where('user_id', $grace->id)->exists())->toBeFalse()
        ->and(DB::table('member_tag_members')->where('user_id', $grace->id)->exists())->toBeFalse();

    $this->actingAs($grace)->get(grantsAcceptUrl($this->community->communityInvitations()->sole()))
        ->assertRedirect(route('communities.show', ['communitySlug' => 'qxcommunity-example']));
    expect($this->community->getMemberById($grace->id)?->role)->toBe(KnowiiCommunityMemberRole::Member)
        ->and($this->paid->members()->whereKey($grace->id)->exists())->toBeFalse()
        ->and($this->founder->members()->whereKey($grace->id)->exists())->toBeFalse()
        ->and(CommunityInvitation::query()->where('email', 'qx-grace@example.com')->exists())->toBeFalse();
});

test('an invitation that expired after the page read it is refused in the transaction of the join (#1390)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-18T10:00:00Z'));
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);
    $invitation = grantsInvitation($this->community, 'qx-grace@example.com', [$this->paid], []);
    // The stored invitation is past its validity; the model that the page read is not, so only the read in the
    // transaction refuses it
    DB::table('community_invitations')->where('id', $invitation->id)->update(['created_at' => CarbonImmutable::parse('2026-11-01T10:00:00Z')]);
    expect($invitation->isExpired())->toBeFalse();

    expect(fn () => app(AcceptsCommunityInvitations::class)->accept($grace, $invitation))->toThrow(BusinessException::class, 'This invitation expired. Ask for a new one');
    expect(User::query()->findOrFail($grace->id)->belongsToCommunity($this->community))->toBeFalse()
        ->and(DB::table('access_group_members')->where('user_id', $grace->id)->exists())->toBeFalse()
        ->and(CommunityInvitation::query()->whereKey($invitation->id)->exists())->toBeTrue();
});

test('a failure while the grants are given leaves no member, no group, no tag, and the invitation stays (#1390)', function () {
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);
    $invitation = grantsInvitation($this->community, 'qx-grace@example.com', [$this->paid], [$this->founder]);
    // The groups are given first, then a tag fails: the trigger stands for any failure of the writer of the tags
    DB::statement("CREATE FUNCTION qx_refuse_tag_link() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'refused by the test'; END; \$\$ LANGUAGE plpgsql");
    DB::statement('CREATE TRIGGER qx_refuse_tag_link BEFORE INSERT ON member_tag_members FOR EACH ROW EXECUTE FUNCTION qx_refuse_tag_link()');

    expect(fn () => app(AcceptsCommunityInvitations::class)->accept($grace, $invitation))->toThrow(QueryException::class);

    expect(User::query()->findOrFail($grace->id)->belongsToCommunity($this->community))->toBeFalse()
        ->and(DB::table('access_group_members')->where('user_id', $grace->id)->exists())->toBeFalse()
        ->and(DB::table('member_tag_members')->where('user_id', $grace->id)->exists())->toBeFalse()
        ->and(CommunityInvitation::query()->whereKey($invitation->id)->exists())->toBeTrue()
        ->and($invitation->accessGroups()->count())->toBe(1);
    Event::assertNotDispatched(AccessGroupsChanged::class);
    Event::assertNotDispatched(SpaceTreeChanged::class);
});

test('a join with a group sends AccessGroupsChanged and SpaceTreeChanged one time each; a join without a group sends none (#1390)', function () {
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);
    $ada = User::factory()->withUserProfile()->create(['email' => 'qx-ada@example.com']);

    app(AcceptsCommunityInvitations::class)->accept($ada, grantsInvitation($this->community, 'qx-ada@example.com', [], [$this->founder]));
    Event::assertNotDispatched(AccessGroupsChanged::class);
    Event::assertNotDispatched(SpaceTreeChanged::class);

    app(AcceptsCommunityInvitations::class)->accept($grace, grantsInvitation($this->community, 'qx-grace@example.com', [$this->paid, $this->alumni], [$this->founder]));
    Event::assertDispatchedTimes(AccessGroupsChanged::class, 1);
    Event::assertDispatchedTimes(SpaceTreeChanged::class, 1);
});

test('the mail of an invitation names no group and no tag (#1390)', function () {
    $invitation = grantsInvitation($this->community, 'qx-grace@example.com', [$this->paid], [$this->founder, $this->onboarded]);

    $html = (new CommunityInvitationMail($invitation, $this->c['owner']))->render();

    expect($html)->toContain('Qxcommunity Example')
        ->not->toContain('Qxgrant')
        ->not->toContain('alumni');
});

// ---------------------------------------------------------------------------------------------------------------------
// The table
// ---------------------------------------------------------------------------------------------------------------------

test('the migration of the links goes down and up again, and changes no other table (#1390)', function () {
    $migration = require database_path('migrations/2026_11_22_200000_create_community_invitation_grants_tables.php');
    $invitation = grantsInvitation($this->community, 'qx@example.com', [$this->paid], [$this->founder]);

    $migration->down();
    expect(Schema::hasTable('community_invitation_access_groups'))->toBeFalse()
        ->and(Schema::hasTable('community_invitation_member_tags'))->toBeFalse()
        ->and(CommunityInvitation::query()->whereKey($invitation->id)->exists())->toBeTrue();

    $migration->up();
    expect(Schema::getColumnListing('community_invitation_access_groups'))->toEqualCanonicalizing(['id', 'community_invitation_id', 'access_group_id', 'created_at'])
        ->and(Schema::getColumnListing('community_invitation_member_tags'))->toEqualCanonicalizing(['id', 'community_invitation_id', 'member_tag_id', 'created_at'])
        ->and(array_column(Schema::getIndexes('community_invitation_access_groups'), 'columns'))->toContain(['access_group_id'], ['community_invitation_id', 'access_group_id'])
        ->and(array_column(Schema::getIndexes('community_invitation_member_tags'), 'columns'))->toContain(['member_tag_id'], ['community_invitation_id', 'member_tag_id']);
});
