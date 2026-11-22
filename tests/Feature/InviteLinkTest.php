<?php

use App\Constants;
use App\Contracts\InviteLinks\CreatesInviteLinks;
use App\Contracts\InviteLinks\DeletesInviteLinks;
use App\Contracts\InviteLinks\JoinsCommunitiesThroughInviteLinks;
use App\Contracts\InviteLinks\UpdatesInviteLinks;
use App\Enums\KnowiiCommunityJoinPolicy;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiInviteLinkStatus;
use App\Enums\KnowiiModeratorPermission;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Exceptions\InviteLinkUnusableException;
use App\Models\AccessGroup;
use App\Models\AccessGroupMember;
use App\Models\AccessGroupRule;
use App\Models\Community;
use App\Models\CommunityJoinBlock;
use App\Models\InviteLink;
use App\Models\InviteLinkUse;
use App\Models\MemberTag;
use App\Models\User;
use App\Support\CommunityRoles;
use App\Support\InviteLinkTexts;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/*
 * Invite links (#1391, ADR 0062): the rights, the fields, the states, the join through a link with its checks, the
 * record of the joins, and the table.
 */

beforeEach(function () {
    // A fixed time: no test reads the real clock
    $this->travelTo(CarbonImmutable::parse('2026-11-23T10:00:00Z'));
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->community->forceFill(['name' => 'Qxlink Community', 'slug' => 'qxlink-community'])->save();
    $this->cohort = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Qxlink Cohort']);
    $this->team = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Qxlink Team']);
    $this->buyer = MemberTag::factory()->forMembers()->create(['community_id' => $this->community->id, 'name' => 'Qxlink Buyer']);
    $this->note = MemberTag::factory()->forManagers()->create(['community_id' => $this->community->id, 'name' => 'Qxlink Note']);
    $this->newcomer = User::factory()->withUserProfile()->create();
    Event::fake([CommunityMembersChanged::class, AccessGroupsChanged::class, SpaceTreeChanged::class]);
});

/**
 * A link of the community with the given groups and tags, as the create writes it
 *
 * @param  list<AccessGroup>  $groups
 * @param  list<MemberTag>  $tags
 * @param  array<string, mixed>  $state
 */
function ilLink(Community $community, array $groups = [], array $tags = [], array $state = []): InviteLink
{
    $link = InviteLink::factory()->create(['community_id' => $community->id, 'name' => 'Qxlink Buyers', ...$state]);
    $link->accessGroups()->attach(array_map(static fn (AccessGroup $group): int => $group->id, $groups));
    $link->memberTags()->attach(array_map(static fn (MemberTag $tag): int => $tag->id, $tags));

    return $link;
}

function ilCreate(User $user, Community $community, array $input): InviteLink
{
    return app(CreatesInviteLinks::class)->create($user, $community, ['name' => 'Qxlink Buyers', ...$input]);
}

function ilJoin(User $user, string $token): Community
{
    return app(JoinsCommunitiesThroughInviteLinks::class)->join($user, $token);
}

function ilIsMember(User $user, Community $community): bool
{
    return User::query()->findOrFail($user->id)->belongsToCommunity($community);
}

/**
 * The rows that a join writes, to prove that a refused join changed nothing
 *
 * @return array{members: int, groups: int, tags: int, uses: int, counts: list<int>}
 */
function ilSnapshot(): array
{
    return [
        'members' => DB::table('community_members')->count(),
        'groups' => DB::table('access_group_members')->count(),
        'tags' => DB::table('member_tag_members')->count(),
        'uses' => DB::table('invite_link_uses')->count(),
        'counts' => DB::table('invite_links')->orderBy('id')->pluck('uses_count')->map(static fn ($count): int => (int) $count)->all(),
    ];
}

// ---------------------------------------------------------------------------------------------------------------------
// Who manages the links
// ---------------------------------------------------------------------------------------------------------------------

test('only the owner and the admins manage the invite links, also a moderator with the right to manage members does not', function (string $who, bool $manageMembers, bool $allowed) {
    if ($manageMembers) {
        $this->community->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_members' => true]]);
    }

    expect(Gate::forUser($this->c[$who]->fresh())->check('manageInviteLinks', $this->community->fresh()))->toBe($allowed);
})->with([
    'the owner' => ['owner', false, true],
    'an admin' => ['admin', false, true],
    'a moderator' => ['moderator', false, false],
    'a moderator with manage members' => ['moderator', true, false],
    'a member' => ['member', false, false],
    'a person who is not a member' => ['outsider', false, false],
]);

test('a moderator with the right to manage members cannot create a link', function () {
    $this->community->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_members' => true]]);

    expect(fn () => ilCreate($this->c['moderator']->fresh(), $this->community->fresh(), []))->toThrow(AuthorizationException::class);
    expect(InviteLink::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------------------------------------------------
// The link and its token
// ---------------------------------------------------------------------------------------------------------------------

test('a new link is on, without an end and without a maximum, gives what it names, and keeps only the hash and the encrypted token', function () {
    $link = ilCreate($this->c['owner'], $this->community, ['access_groups' => [$this->cohort->cuid], 'member_tags' => [$this->buyer->cuid, $this->note->cuid]]);

    expect($link->active)->toBeTrue()
        ->and($link->expires_at)->toBeNull()
        ->and($link->max_uses)->toBeNull()
        ->and($link->uses_count)->toBe(0)
        ->and($link->status())->toBe(KnowiiInviteLinkStatus::Active)
        ->and($link->token)->toMatch('/\A[A-Za-z0-9]{40}\z/')
        ->and($link->token_hash)->toBe(hash('sha256', $link->token))
        ->and($link->url())->toBe(url('/invite/'.$link->token))
        ->and($link->accessGroups->pluck('cuid')->all())->toBe([$this->cohort->cuid])
        ->and($link->memberTags->pluck('cuid')->all())->toBe([$this->buyer->cuid, $this->note->cuid]);

    // At rest the token is encrypted: the column never holds it in clear
    $stored = DB::table('invite_links')->where('id', $link->id)->first();
    expect($stored->token)->not->toBe($link->token)->and($stored->token)->not->toContain($link->token);
});

test('a link is found by its token through the hash only, and a text that is not a token finds nothing', function () {
    $link = ilLink($this->community);

    expect(InviteLink::findByToken($link->token)?->id)->toBe($link->id)
        ->and(InviteLink::findByToken(strtoupper($link->token) === $link->token ? strtolower($link->token) : strtoupper($link->token)))->toBeNull()
        ->and(InviteLink::findByToken(substr($link->token, 0, 39)))->toBeNull()
        ->and(InviteLink::findByToken($link->token.'a'))->toBeNull()
        ->and(InviteLink::findByToken($link->token_hash))->toBeNull()
        ->and(InviteLink::findByToken(''))->toBeNull();

    // The lookup reads `token_hash`, never `token` (which holds an encrypted value)
    DB::enableQueryLog();
    InviteLink::findByToken($link->token);
    $sql = collect(DB::getQueryLog())->pluck('query')->implode(' ');
    expect($sql)->toContain('"token_hash" = ?')->and($sql)->not->toContain('"token" =');
});

test('the name is cleaned as a tag name and holds 2 to 64 characters as mb_strlen counts', function () {
    $link = ilCreate($this->c['owner'], $this->community, ['name' => "  Course \u{00A0}  buyers  "]);
    expect($link->name)->toBe('Course buyers');

    $long = ilCreate($this->c['owner'], $this->community, ['name' => str_repeat('é', Constants::MAX_LENGTH_INVITE_LINK_NAME)]);
    expect(mb_strlen($long->name))->toBe(64);

    foreach (['a', str_repeat('b', 65), "Two\nlines", "Tab\tname", "Hidden\u{200B}mark", 42] as $name) {
        expect(fn () => ilCreate($this->c['owner'], $this->community, ['name' => $name]))->toThrow(ValidationException::class);
    }
    expect(fn () => app(CreatesInviteLinks::class)->create($this->c['owner'], $this->community, []))->toThrow(ValidationException::class);
});

test('the names of two links can be the same', function () {
    ilCreate($this->c['owner'], $this->community, []);
    ilCreate($this->c['admin'], $this->community, []);

    expect(InviteLink::query()->where('name', 'Qxlink Buyers')->count())->toBe(2);
});

test('the fields are checked: groups and tags of the community, no group that a rule feeds, 20 tags, an end in the future, 1 to 10000 joins, a strict boolean', function (array $input, string $field, ?string $message) {
    $other = Community::factory()->create();
    $foreign = AccessGroup::query()->create(['community_id' => $other->id, 'name' => 'Qxlink Foreign']);
    $tags = MemberTag::factory()->count(21)->sequence(fn ($sequence) => ['name' => 'Qxlink Tag '.$sequence->index])->create(['community_id' => $this->community->id]);
    AccessGroupRule::query()->create(['access_group_id' => $this->team->id, 'entitlement_key' => 'example.paid']);
    $input = array_map(fn ($value) => match ($value) {
        'FOREIGN' => [$foreign->cuid],
        'RULED' => [$this->team->cuid],
        'TAGS21' => $tags->pluck('cuid')->all(),
        default => $value,
    }, $input);

    try {
        ilCreate($this->c['owner'], $this->community, $input);
        $this->fail('The link was created');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe([$field]);
        if ($message !== null) {
            expect($e->errors()[$field][0])->toBe($message);
        }
    }
    expect(InviteLink::query()->count())->toBe(0);
})->with([
    'a group of another community' => [['access_groups' => 'FOREIGN'], 'access_groups', 'No access group of the community matches'],
    'a group that a rule feeds' => [['access_groups' => 'RULED'], 'access_groups', 'A link cannot give a group that a rule on an entitlement feeds'],
    '21 tags' => [['member_tags' => 'TAGS21'], 'member_tags', 'An invite link holds at most 20 tags'],
    'an end in the past' => [['expires_at' => '2026-11-23T09:59:59Z'], 'expires_at', 'The end must be in the future'],
    'an end now' => [['expires_at' => '2026-11-23T10:00:00Z'], 'expires_at', 'The end must be in the future'],
    'an end without a zone' => [['expires_at' => '2026-12-01 10:00'], 'expires_at', null],
    'an end that is not a time' => [['expires_at' => 'tomorrow'], 'expires_at', null],
    'a maximum of 0' => [['max_uses' => 0], 'max_uses', null],
    'a maximum of 10001' => [['max_uses' => 10001], 'max_uses', null],
    'a maximum that is true' => [['max_uses' => true], 'max_uses', null],
    'a maximum with a fraction' => [['max_uses' => 1.5], 'max_uses', null],
    'active as a text' => [['active' => 'true'], 'active', null],
    'active as a number' => [['active' => 1], 'active', null],
]);

test('the rule on an entitlement refuses its group whether the feature of the rules is on or not', function () {
    config(['services.oidc.entitlements_url' => null]);
    AccessGroupRule::query()->create(['access_group_id' => $this->team->id, 'entitlement_key' => 'example.paid']);

    expect(fn () => ilCreate($this->c['owner'], $this->community, ['access_groups' => [$this->team->cuid]]))->toThrow(ValidationException::class);
});

test('an end in the future and a maximum are kept, and null clears them', function () {
    $link = ilCreate($this->c['owner'], $this->community, ['expires_at' => '2026-11-30T10:00:00+01:00', 'max_uses' => '100']);
    expect($link->expires_at?->toIso8601ZuluString())->toBe('2026-11-30T09:00:00Z')->and($link->max_uses)->toBe(100);

    $link = app(UpdatesInviteLinks::class)->update($this->c['owner'], $link, ['expires_at' => null, 'max_uses' => null]);
    expect($link->expires_at)->toBeNull()->and($link->max_uses)->toBe(null);
});

test('a community has at most 50 links, whatever their state', function () {
    InviteLink::factory()->count(Constants::MAX_INVITE_LINKS_PER_COMMUNITY - 2)->create(['community_id' => $this->community->id]);
    InviteLink::factory()->off()->create(['community_id' => $this->community->id]);
    InviteLink::factory()->ended()->create(['community_id' => $this->community->id]);

    expect(fn () => ilCreate($this->c['owner'], $this->community, []))->toThrow(BusinessException::class, 'A community can have at most 50 invite links');
    expect(InviteLink::query()->count())->toBe(50);
});

test('a personal community has no invite link', function () {
    $personal = Community::factory()->create(['owner_id' => $this->c['owner']->id, 'visibility' => KnowiiCommunityVisibility::Personal]);

    try {
        ilCreate($this->c['owner'], $personal, []);
        $this->fail('The link was created');
    } catch (ValidationException $e) {
        expect($e->errors())->toBe(['community' => ['A personal community has no invite link']]);
    }
});

// ---------------------------------------------------------------------------------------------------------------------
// The states
// ---------------------------------------------------------------------------------------------------------------------

test('the state of a link is off, else ended, else full, else active', function (array $state, KnowiiInviteLinkStatus $status) {
    $link = ilLink($this->community, state: $state);
    $link->forceFill(['uses_count' => $state['uses_count'] ?? 0])->save();

    expect($link->fresh()?->status())->toBe($status);
})->with([
    'active' => [[], KnowiiInviteLinkStatus::Active],
    'active with an end later and room left' => [['expires_at' => '2026-11-23T10:00:01Z', 'max_uses' => 2, 'uses_count' => 1], KnowiiInviteLinkStatus::Active],
    'off' => [['active' => false], KnowiiInviteLinkStatus::Off],
    'off before ended and full' => [['active' => false, 'expires_at' => '2026-11-22T10:00:00Z', 'max_uses' => 1, 'uses_count' => 1], KnowiiInviteLinkStatus::Off],
    'ended at its end' => [['expires_at' => '2026-11-23T10:00:00Z'], KnowiiInviteLinkStatus::Ended],
    'ended before full' => [['expires_at' => '2026-11-22T10:00:00Z', 'max_uses' => 1, 'uses_count' => 1], KnowiiInviteLinkStatus::Ended],
    'full' => [['max_uses' => 3, 'uses_count' => 3], KnowiiInviteLinkStatus::Full],
    'full past its maximum' => [['max_uses' => 2, 'uses_count' => 3], KnowiiInviteLinkStatus::Full],
]);

test('a link that is off, ended, full, deleted or unknown lets nobody join, with one answer for all', function (string $case) {
    $link = match ($case) {
        'off' => ilLink($this->community, state: ['active' => false]),
        'ended' => ilLink($this->community, state: ['expires_at' => '2026-11-23T09:00:00Z']),
        'full' => InviteLink::factory()->full()->create(['community_id' => $this->community->id]),
        'deleted', 'unknown', 'of a deleted community' => ilLink($this->community),
    };
    $token = $link->token;
    match ($case) {
        'deleted' => $link->delete(),
        'unknown' => $token = InviteLink::newToken(),
        'of a deleted community' => $this->community->delete(),
        default => null,
    };
    $before = ilSnapshot();

    expect(fn () => ilJoin($this->newcomer, $token))->toThrow(InviteLinkUnusableException::class, InviteLinkTexts::UNUSABLE);
    expect(ilSnapshot())->toBe($before);
})->with(['off', 'ended', 'full', 'deleted', 'unknown', 'of a deleted community']);

test('a link of a personal community lets nobody join', function () {
    $personal = Community::factory()->create(['owner_id' => $this->c['owner']->id, 'visibility' => KnowiiCommunityVisibility::Personal]);
    $link = InviteLink::factory()->create(['community_id' => $personal->id]);

    expect(fn () => ilJoin($this->newcomer, $link->token))->toThrow(InviteLinkUnusableException::class);
    expect(ilIsMember($this->newcomer, $personal))->toBeFalse();
});

test('a link that is off works for nobody, and on again it works again', function () {
    $link = ilLink($this->community);
    app(UpdatesInviteLinks::class)->update($this->c['admin'], $link, ['active' => false]);
    expect(fn () => ilJoin($this->newcomer, $link->token))->toThrow(InviteLinkUnusableException::class);

    app(UpdatesInviteLinks::class)->update($this->c['admin'], $link->fresh(), ['active' => true]);
    ilJoin($this->newcomer, $link->token);

    expect(ilIsMember($this->newcomer, $this->community))->toBeTrue();
});

test('a maximum set at or below the count makes the link full', function () {
    $link = ilLink($this->community);
    ilJoin($this->newcomer, $link->token);

    $link = app(UpdatesInviteLinks::class)->update($this->c['owner'], $link->fresh(), ['max_uses' => 1]);

    expect($link->status())->toBe(KnowiiInviteLinkStatus::Full);
    expect(fn () => ilJoin($this->c['outsider'], $link->token))->toThrow(InviteLinkUnusableException::class);
});

// ---------------------------------------------------------------------------------------------------------------------
// The join
// ---------------------------------------------------------------------------------------------------------------------

test('a person who joins through a link becomes a member with the groups by hand and the tags, the count goes up and the join is recorded', function () {
    $link = ilLink($this->community, [$this->cohort, $this->team], [$this->buyer, $this->note]);
    $updatedAt = $link->updated_at->toIso8601String();
    $this->travel(5)->minutes();

    $joined = ilJoin($this->newcomer, $link->token);

    expect($joined->id)->toBe($this->community->id)
        ->and(CommunityRoles::roleName(User::query()->findOrFail($this->newcomer->id), $this->community->fresh()))->toBe('member')
        ->and(AccessGroupMember::query()->where('user_id', $this->newcomer->id)->orderBy('access_group_id')->get(['access_group_id', 'manual', 'by_rule'])->map(static fn ($row): array => [$row->access_group_id, $row->manual, $row->by_rule])->all())
        ->toBe([[$this->cohort->id, true, false], [$this->team->id, true, false]])
        ->and(DB::table('member_tag_members')->where('user_id', $this->newcomer->id)->orderBy('member_tag_id')->pluck('member_tag_id')->all())->toBe([$this->buyer->id, $this->note->id]);

    $fresh = $link->fresh();
    expect($fresh?->uses_count)->toBe(1)
        // Only the count changes: the time of the last change of the settings stays
        ->and($fresh?->updated_at->toIso8601String())->toBe($updatedAt)
        ->and(InviteLinkUse::query()->get(['invite_link_id', 'user_id'])->map(static fn ($use): array => [$use->invite_link_id, $use->user_id])->all())->toBe([[$link->id, $this->newcomer->id]])
        ->and(InviteLinkUse::query()->firstOrFail()->created_at->toIso8601ZuluString())->toBe('2026-11-23T10:05:00Z');
});

test('the join sends the events of the members and of the groups after the commit, and none of the groups without a group', function () {
    $link = ilLink($this->community, [$this->cohort]);
    ilJoin($this->newcomer, $link->token);

    Event::assertDispatchedTimes(CommunityMembersChanged::class, 1);
    Event::assertDispatched(CommunityMembersChanged::class, fn (CommunityMembersChanged $event) => $event->communityCuid === $this->community->cuid);
    Event::assertDispatchedTimes(AccessGroupsChanged::class, 1);
    Event::assertDispatchedTimes(SpaceTreeChanged::class, 1);

    $other = ilLink($this->community, [], [$this->buyer]);
    ilJoin($this->c['outsider'], $other->token);

    Event::assertDispatchedTimes(CommunityMembersChanged::class, 2);
    Event::assertDispatchedTimes(AccessGroupsChanged::class, 1);
});

test('the join gives the role member in a community by invitation and in an open community alike', function (KnowiiCommunityJoinPolicy $policy) {
    $this->community->update(['join_policy' => $policy]);
    $link = ilLink($this->community);

    ilJoin($this->newcomer, $link->token);

    expect(CommunityRoles::roleName(User::query()->findOrFail($this->newcomer->id), $this->community->fresh()))->toBe('member');
})->with([KnowiiCommunityJoinPolicy::Invitation, KnowiiCommunityJoinPolicy::Open]);

test('a member who opens a link gets a refusal, and nothing changes: no group, no tag, no count, no row', function (string $who) {
    $link = ilLink($this->community, [$this->cohort], [$this->buyer]);
    $before = ilSnapshot();

    expect(fn () => ilJoin($this->c[$who], $link->token))->toThrow(BusinessException::class, 'You are already a member of this community');

    expect(ilSnapshot())->toBe($before)
        ->and(CommunityRoles::roleName(User::query()->findOrFail($this->c[$who]->id), $this->community->fresh()))->toBe($who);
})->with(['owner', 'admin', 'member']);

test('a person whom a manager removed cannot come back through a link: the block stays and nothing is counted', function () {
    $link = ilLink($this->community, [$this->cohort], [$this->buyer]);
    CommunityJoinBlock::query()->create(['community_id' => $this->community->id, 'user_id' => $this->newcomer->id]);
    $before = ilSnapshot();

    expect(fn () => ilJoin($this->newcomer, $link->token))->toThrow(BusinessException::class, 'You cannot join this community by yourself. Ask its managers for an invitation.');

    expect(ilSnapshot())->toBe($before)
        ->and(CommunityJoinBlock::query()->where('user_id', $this->newcomer->id)->exists())->toBeTrue()
        ->and(ilIsMember($this->newcomer, $this->community))->toBeFalse();
});

test('an account without a verified email address cannot join through a link', function () {
    $unverified = User::factory()->withUserProfile()->unverified()->create();
    $link = ilLink($this->community);

    expect(fn () => ilJoin($unverified, $link->token))->toThrow(BusinessException::class, 'Verify your email address before you join a community');
    expect(ilIsMember($unverified, $this->community))->toBeFalse()->and($link->fresh()?->uses_count)->toBe(0);
});

test('a group that a rule feeds since the save of the link is not given at the join, the other groups are', function () {
    $link = ilLink($this->community, [$this->cohort, $this->team]);
    AccessGroupRule::query()->create(['access_group_id' => $this->team->id, 'entitlement_key' => 'example.paid']);

    ilJoin($this->newcomer, $link->token);

    expect(AccessGroupMember::query()->where('user_id', $this->newcomer->id)->pluck('access_group_id')->all())->toBe([$this->cohort->id]);
});

test('two people at the last use: one joins, the other gets the answer of a link that does not work', function () {
    $link = ilLink($this->community, state: ['max_uses' => 1]);
    // Each join reads its own model of the link, as two requests do
    $token = InviteLink::query()->findOrFail($link->id)->token;

    ilJoin($this->newcomer, $token);
    expect(fn () => ilJoin($this->c['outsider'], InviteLink::query()->findOrFail($link->id)->token))->toThrow(InviteLinkUnusableException::class);

    expect($link->fresh()?->uses_count)->toBe(1)
        ->and(InviteLinkUse::query()->count())->toBe(1)
        ->and(ilIsMember($this->c['outsider'], $this->community))->toBeFalse();
});

test('two clicks of one person give one membership and one count', function () {
    $link = ilLink($this->community, [$this->cohort], [$this->buyer]);

    ilJoin($this->newcomer, $link->token);
    expect(fn () => ilJoin(User::query()->findOrFail($this->newcomer->id), $link->token))->toThrow(BusinessException::class, 'You are already a member of this community');

    expect(DB::table('community_members')->where('user_id', $this->newcomer->id)->count())->toBe(1)
        ->and($link->fresh()?->uses_count)->toBe(1)
        ->and(InviteLinkUse::query()->count())->toBe(1);
});

test('a member who leaves stays counted, and joins again through the link with a new record', function () {
    $link = ilLink($this->community, [$this->cohort], [$this->buyer]);
    ilJoin($this->newcomer, $link->token);

    $this->community->removeUser($this->newcomer);
    expect($link->fresh()?->uses_count)->toBe(1)
        ->and(InviteLinkUse::query()->count())->toBe(0)
        ->and(AccessGroupMember::query()->where('user_id', $this->newcomer->id)->count())->toBe(0);

    ilJoin(User::query()->findOrFail($this->newcomer->id), $link->token);
    expect($link->fresh()?->uses_count)->toBe(2)->and(InviteLinkUse::query()->count())->toBe(1);
});

test('a removal of a member deletes their records of the links of that community only', function () {
    $other = communityWithMembers()['community'];
    $here = ilLink($this->community);
    $there = InviteLink::factory()->create(['community_id' => $other->id]);
    ilJoin($this->newcomer, $here->token);
    ilJoin(User::query()->findOrFail($this->newcomer->id), $there->token);

    $this->community->removeUser($this->newcomer);

    expect(InviteLinkUse::query()->pluck('invite_link_id')->all())->toBe([$there->id]);
});

// ---------------------------------------------------------------------------------------------------------------------
// A change and a delete of a link
// ---------------------------------------------------------------------------------------------------------------------

test('a change of the groups and the tags replaces the lists and changes nothing for the people who joined before', function () {
    $link = ilLink($this->community, [$this->cohort], [$this->buyer]);
    ilJoin($this->newcomer, $link->token);

    $link = app(UpdatesInviteLinks::class)->update($this->c['owner'], $link->fresh(), ['access_groups' => [$this->team->cuid], 'member_tags' => []]);

    expect($link->accessGroups->pluck('cuid')->all())->toBe([$this->team->cuid])
        ->and($link->memberTags)->toHaveCount(0)
        ->and(AccessGroupMember::query()->where('user_id', $this->newcomer->id)->pluck('access_group_id')->all())->toBe([$this->cohort->id])
        ->and(DB::table('member_tag_members')->where('user_id', $this->newcomer->id)->pluck('member_tag_id')->all())->toBe([$this->buyer->id]);
});

test('an update changes only the fields of the request', function () {
    $link = ilLink($this->community, [$this->cohort], [$this->buyer], ['max_uses' => 5]);

    $link = app(UpdatesInviteLinks::class)->update($this->c['admin'], $link, ['name' => '  Newsletter   readers ']);

    expect($link->name)->toBe('Newsletter readers')
        ->and($link->max_uses)->toBe(5)
        ->and($link->active)->toBeTrue()
        ->and($link->accessGroups->pluck('cuid')->all())->toBe([$this->cohort->cuid])
        ->and($link->memberTags->pluck('cuid')->all())->toBe([$this->buyer->cuid]);
});

test('an update cannot give a group that a rule feeds', function () {
    $link = ilLink($this->community);
    AccessGroupRule::query()->create(['access_group_id' => $this->team->id, 'entitlement_key' => 'example.paid']);

    expect(fn () => app(UpdatesInviteLinks::class)->update($this->c['owner'], $link, ['access_groups' => [$this->team->cuid]]))
        ->toThrow(ValidationException::class, 'A link cannot give a group that a rule on an entitlement feeds');
});

test('a delete stops the link and takes its list of who joined, and the members keep their groups and tags', function () {
    $link = ilLink($this->community, [$this->cohort], [$this->buyer]);
    ilJoin($this->newcomer, $link->token);

    app(DeletesInviteLinks::class)->delete($this->c['admin'], $link->fresh());

    expect(InviteLink::query()->count())->toBe(0)
        ->and(InviteLinkUse::query()->count())->toBe(0)
        ->and(DB::table('invite_link_access_groups')->count())->toBe(0)
        ->and(DB::table('invite_link_member_tags')->count())->toBe(0)
        ->and(ilIsMember($this->newcomer, $this->community))->toBeTrue()
        ->and(AccessGroupMember::query()->where('user_id', $this->newcomer->id)->count())->toBe(1)
        ->and(DB::table('member_tag_members')->where('user_id', $this->newcomer->id)->count())->toBe(1);
    expect(fn () => ilJoin($this->c['outsider'], $link->token))->toThrow(InviteLinkUnusableException::class);
});

test('a deleted group or tag leaves every link', function () {
    $link = ilLink($this->community, [$this->cohort, $this->team], [$this->buyer, $this->note]);

    $this->cohort->delete();
    $this->note->delete();

    $fresh = InviteLink::query()->with(['accessGroups', 'memberTags'])->findOrFail($link->id);
    expect($fresh->accessGroups->pluck('id')->all())->toBe([$this->team->id])
        ->and($fresh->memberTags->pluck('id')->all())->toBe([$this->buyer->id]);
});

test('the deletion of a community deletes its links and their records', function () {
    $link = ilLink($this->community, [$this->cohort], [$this->buyer]);
    ilJoin($this->newcomer, $link->token);

    $this->community->delete();

    expect(InviteLink::query()->count())->toBe(0)->and(InviteLinkUse::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------------------------------------------------
// No token in the logs
// ---------------------------------------------------------------------------------------------------------------------

test('the log lines of a create, an update, a join and a delete name cuids only, never the token, its hash or the name', function () {
    $lines = [];
    Log::listen(static function ($message) use (&$lines) {
        $lines[] = $message->message.' '.json_encode($message->context);
    });

    $link = ilCreate($this->c['owner'], $this->community, ['access_groups' => [$this->cohort->cuid]]);
    app(UpdatesInviteLinks::class)->update($this->c['owner'], $link, ['max_uses' => 10]);
    ilJoin($this->newcomer, $link->token);
    app(DeletesInviteLinks::class)->delete($this->c['owner'], $link->fresh());

    $all = implode("\n", $lines);
    expect($all)->toContain('Invite link created')
        ->and($all)->toContain('Member joined the community through an invite link')
        ->and($all)->toContain($link->cuid)
        ->and($all)->not->toContain($link->token)
        ->and($all)->not->toContain($link->token_hash)
        ->and($all)->not->toContain('Qxlink Buyers');
});

// ---------------------------------------------------------------------------------------------------------------------
// The tables
// ---------------------------------------------------------------------------------------------------------------------

test('the tables refuse a maximum under 1 and a negative count, and keep one record per link and member', function () {
    $link = ilLink($this->community);

    // Each statement in a transaction of its own: a refused statement ends only that one
    expect(fn () => DB::transaction(static fn () => DB::table('invite_links')->where('id', $link->id)->update(['max_uses' => 0])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(static fn () => DB::table('invite_links')->where('id', $link->id)->update(['uses_count' => -1])))->toThrow(QueryException::class);

    InviteLinkUse::query()->create(['invite_link_id' => $link->id, 'user_id' => $this->newcomer->id]);
    expect(fn () => DB::transaction(fn () => InviteLinkUse::query()->create(['invite_link_id' => $link->id, 'user_id' => $this->newcomer->id])))->toThrow(QueryException::class);
});

test('the migration of the invite links goes down and up again', function () {
    $migration = require database_path('migrations/2026_11_23_100000_create_invite_links_tables.php');

    $migration->down();
    expect(Schema::hasTable('invite_links'))->toBeFalse()
        ->and(Schema::hasTable('invite_link_access_groups'))->toBeFalse()
        ->and(Schema::hasTable('invite_link_member_tags'))->toBeFalse()
        ->and(Schema::hasTable('invite_link_uses'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('invite_links'))->toBeTrue()->and(Schema::hasTable('invite_link_uses'))->toBeTrue();
});
