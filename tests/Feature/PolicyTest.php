<?php

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiModeratorPermission;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\AccessGroup;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Event;
use App\Models\EventAnswer;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/*
 * One matrix per policy: for each ability, the roles that are allowed. Every other role must be refused.
 * Roles: owner, admin, moderator, member (of the community), outsider (signed in, not a member), guest (not signed in).
 */

const POLICY_ALL_ROLES = ['owner', 'admin', 'moderator', 'member', 'outsider', 'guest'];
const POLICY_MEMBERS = ['owner', 'admin', 'moderator', 'member'];
const POLICY_MODERATORS = ['owner', 'admin', 'moderator'];

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->c['guest'] = null;
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->membersSpace = Space::factory()->inGroup($group)->create([
        'visibility' => KnowiiSpaceVisibility::Members,
        'posting_policy' => KnowiiSpacePostingPolicy::Members,
    ]);
    $this->publicModeratedSpace = Space::factory()->inGroup($group)->create([
        'visibility' => KnowiiSpaceVisibility::Public,
        'posting_policy' => KnowiiSpacePostingPolicy::Moderators,
    ]);
    // A chat space stays for signed-in people, also when it is public (#1387)
    $this->publicChatSpace = Space::factory()->inGroup($group)->create([
        'type' => KnowiiSpaceType::Chat,
        'visibility' => KnowiiSpaceVisibility::Public,
    ]);
    // The member writes the post and the comment
    $this->post = Post::factory()->inSpace($this->membersSpace)->create(['author_id' => $this->c['member']->id]);
    $this->publicPost = Post::factory()->inSpace($this->publicModeratedSpace)->create(['author_id' => $this->c['moderator']->id]);
    $this->comment = Comment::factory()->onPost($this->post)->create(['author_id' => $this->c['member']->id]);
});

/**
 * Assert that exactly the given roles have the ability.
 *
 * @param  list<string>  $allowed
 * @param  array<int, mixed>  $arguments
 */
function expectAllowedRoles(array $users, string $ability, array $arguments, array $allowed): void
{
    foreach (POLICY_ALL_ROLES as $role) {
        /** @var User|null $user */
        $user = $users[$role];
        $result = Gate::forUser($user)->allows($ability, $arguments);
        expect($result)->toBe(in_array($role, $allowed, true), sprintf('%s %s "%s"', $role, in_array($role, $allowed, true) ? 'must have' : 'must not have', $ability));
    }
}

test('community abilities per role', function (string $ability, array $allowed) {
    expectAllowedRoles($this->c, $ability, [$this->c['community']], $allowed);
})->with([
    'view' => ['view', POLICY_MEMBERS],
    'update' => ['update', ['owner']],
    'manage' => ['manage', ['owner', 'admin']],
    'manageSpaces' => ['manageSpaces', ['owner', 'admin']],
    'uploadImage' => ['uploadImage', POLICY_MEMBERS],
    'manageModeratorPermissions' => ['manageModeratorPermissions', ['owner', 'admin']],
    'manageJoinPolicy' => ['manageJoinPolicy', ['owner', 'admin']],
    'manageIndexing' => ['manageIndexing', ['owner', 'admin']],
    'addCommunityMember' => ['addCommunityMember', ['owner', 'admin']],
    'updateCommunityMember' => ['updateCommunityMember', ['owner', 'admin']],
    'removeCommunityMember' => ['removeCommunityMember', ['owner', 'admin']],
    'delete' => ['delete', ['owner']],
    'leave' => ['leave', ['admin', 'moderator', 'member']],
]);

test('every signed-in user can create a community, nobody lists all communities', function () {
    expectAllowedRoles($this->c, 'create', [Community::class], ['owner', 'admin', 'moderator', 'member', 'outsider']);
    expectAllowedRoles($this->c, 'viewAny', [Community::class], []);
});

test('space abilities per role', function (string $ability, string $space, array $allowed) {
    expectAllowedRoles($this->c, $ability, [$this->{$space}], $allowed);
})->with([
    'view a members-only space' => ['view', 'membersSpace', POLICY_MEMBERS],
    'view a public space' => ['view', 'publicModeratedSpace', POLICY_ALL_ROLES],
    'view a public chat space: not a visitor' => ['view', 'publicChatSpace', ['owner', 'admin', 'moderator', 'member', 'outsider']],
    'post in a space open to members' => ['contribute', 'membersSpace', POLICY_MEMBERS],
    'post in a moderators-only space' => ['contribute', 'publicModeratedSpace', POLICY_MODERATORS],
    'moderate a space' => ['moderate', 'membersSpace', POLICY_MODERATORS],
]);

test('post abilities per role', function (string $ability, string $post, array $allowed) {
    expectAllowedRoles($this->c, $ability, [$this->{$post}], $allowed);
})->with([
    'view a post of a members-only space' => ['view', 'post', POLICY_MEMBERS],
    'view a post of a public space' => ['view', 'publicPost', POLICY_ALL_ROLES],
    'edit: the author only' => ['update', 'post', ['member']],
    'delete: the author and the moderators' => ['delete', 'post', ['owner', 'admin', 'moderator', 'member']],
    'delete a post of a moderator: the moderators only' => ['delete', 'publicPost', POLICY_MODERATORS],
    'comment' => ['comment', 'post', POLICY_MEMBERS],
    'comment on a public post: members only' => ['comment', 'publicPost', POLICY_MEMBERS],
    'react' => ['react', 'post', POLICY_MEMBERS],
    'pin' => ['pin', 'post', POLICY_MODERATORS],
]);

/**
 * Events (#1513, ADR 0053): an events space open to members, with a published event and a draft that the member hosts;
 * a public events space where only moderators post, with an event of the admin, and one whose address is for the people
 * who go.
 */
function policyEvents(array $c): array
{
    $group = SpaceGroup::factory()->create(['community_id' => $c['community']->id]);
    $members = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Members, 'posting_policy' => KnowiiSpacePostingPolicy::Members]);
    $public = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Public, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);

    return [
        'membersEvents' => $members,
        'publicEvents' => $public,
        'event' => Event::factory()->inSpace($members)->hostedBy($c['member'])->create(),
        'draftEvent' => Event::factory()->inSpace($members)->hostedBy($c['member'])->draft()->create(),
        'publicEvent' => Event::factory()->inSpace($public)->hostedBy($c['admin'])->create(),
        'goingEvent' => Event::factory()->inSpace($public)->hostedBy($c['moderator'])->create(['location_visibility' => KnowiiEventLocationVisibility::Going]),
    ];
}

test('event abilities per role', function (string $ability, string $item, array $allowed) {
    $events = policyEvents($this->c);
    expectAllowedRoles($this->c, $ability, [$events[$item]], $allowed);
})->with([
    'create in an events space open to members' => ['contribute', 'membersEvents', POLICY_MEMBERS],
    'create in a moderators-only events space' => ['contribute', 'publicEvents', POLICY_MODERATORS],
    'view a published event of a members-only space' => ['view', 'event', POLICY_MEMBERS],
    'view a published event of a public space' => ['view', 'publicEvent', POLICY_ALL_ROLES],
    'view a draft: the host, the owner and the admins' => ['view', 'draftEvent', ['owner', 'admin', 'member']],
    'edit, publish, back to draft, duplicate: the host, the owner and the admins' => ['update', 'event', ['owner', 'admin', 'member']],
    'edit the event of the admin: not a moderator' => ['update', 'publicEvent', ['owner', 'admin']],
    'cancel: the host, the owner, the admins and the moderators' => ['cancel', 'event', POLICY_MEMBERS],
    'cancel the event of the admin: the moderators too' => ['cancel', 'publicEvent', POLICY_MODERATORS],
    'cancel a draft: never a moderator who may not see it' => ['cancel', 'draftEvent', ['owner', 'admin', 'member']],
    'delete a draft: never a moderator who may not see it' => ['delete', 'draftEvent', ['owner', 'admin', 'member']],
    'delete: the host, the owner, the admins and the moderators' => ['delete', 'event', POLICY_MEMBERS],
    'delete the event of the admin: the moderators too' => ['delete', 'publicEvent', POLICY_MODERATORS],
    'the address with members: every member, never an outsider or a visitor' => ['viewLocation', 'publicEvent', POLICY_MEMBERS],
    'the address with going: the host, the owner and the admins (and the members who answered Yes, below)' => ['viewLocation', 'goingEvent', ['owner', 'admin', 'moderator']],
    'the address of a draft: who may view it' => ['viewLocation', 'draftEvent', ['owner', 'admin', 'member']],
]);

test('a moderator without the right to delete content cancels and deletes only their own events', function () {
    $events = policyEvents($this->c);
    $this->c['community']->update(['moderator_permissions' => ['delete_content' => false]]);
    $moderator = User::findOrFail($this->c['moderator']->id);
    $event = Event::query()->findOrFail($events['publicEvent']->id);

    expect(Gate::forUser($moderator)->allows('cancel', $event))->toBeFalse()
        ->and(Gate::forUser($moderator)->allows('delete', $event))->toBeFalse();
});

/**
 * The answers to events (#1515, ADR 0053): who answers an event, and who reads the answers of the others. The host and
 * the member of the access group are plain members; in the groups space, the access group holds them and nobody else.
 */
const POLICY_EVENT_READERS = ['owner', 'admin', 'moderator', 'host', 'member', 'inGroup', 'outsider', 'guest'];

test('the answers to an event per role, per visibility of its space and per state', function (string $visibility, string $state, array $answer, array $viewAnswers) {
    $c = [...$this->c, 'guest' => null];
    foreach (['host', 'inGroup'] as $key) {
        $c[$key] = User::factory()->withUserProfile()->create();
        $c['community']->users()->attach($c[$key], ['role' => KnowiiCommunityMemberRole::Member]);
    }
    $group = SpaceGroup::factory()->create(['community_id' => $c['community']->id]);
    $space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => $visibility]);
    if ($visibility === 'groups') {
        $buyers = AccessGroup::query()->create(['community_id' => $c['community']->id, 'name' => 'Buyers']);
        $space->accessGroups()->attach($buyers);
        $buyers->members()->attach([$c['host']->id, $c['inGroup']->id]);
    }
    $factory = Event::factory()->inSpace($space)->hostedBy($c['host']);
    $event = match ($state) {
        'draft' => $factory->draft()->create(),
        'cancelled' => $factory->cancelled()->create(),
        'past' => $factory->past()->create(),
        'started' => $factory->create(['starts_at' => now()->subMinutes(10), 'ends_at' => now()->addMinutes(50)]),
        default => $factory->create(),
    };

    foreach (POLICY_EVENT_READERS as $role) {
        expect(Gate::forUser($c[$role])->allows('answer', $event))->toBe(in_array($role, $answer, true), "{$role}, answer")
            ->and(Gate::forUser($c[$role])->allows('viewAnswers', $event))->toBe(in_array($role, $viewAnswers, true), "{$role}, viewAnswers");
    }
})->with([
    'a public space: every member answers, never an outsider or a visitor' => ['public', 'published', ['owner', 'admin', 'moderator', 'host', 'member', 'inGroup'], ['owner', 'admin', 'host']],
    'a members space' => ['members', 'published', ['owner', 'admin', 'moderator', 'host', 'member', 'inGroup'], ['owner', 'admin', 'host']],
    'a groups space: the members of the group, the owner and the admins' => ['groups', 'published', ['owner', 'admin', 'host', 'inGroup'], ['owner', 'admin', 'host']],
    'an event that started and did not end' => ['members', 'started', ['owner', 'admin', 'moderator', 'host', 'member', 'inGroup'], ['owner', 'admin', 'host']],
    'a cancelled event: no answer, the answers are still read' => ['public', 'cancelled', [], ['owner', 'admin', 'host']],
    'an event that ended: no answer, the answers are still read' => ['members', 'past', [], ['owner', 'admin', 'host']],
    'a past event of a groups space' => ['groups', 'past', [], ['owner', 'admin', 'host']],
    'a draft has no answers' => ['members', 'draft', [], []],
    'a draft of a public space' => ['public', 'draft', [], []],
]);

/**
 * The readers of an event of a space of the given visibility, and the event in the given state (#1515, #1516)
 *
 * @return array{0: array<string, mixed>, 1: Event}
 */
function policyEventOf(array $c, string $visibility, string $state): array
{
    $c = [...$c, 'guest' => null];
    foreach (['host', 'inGroup'] as $key) {
        $c[$key] = User::factory()->withUserProfile()->create();
        $c['community']->users()->attach($c[$key], ['role' => KnowiiCommunityMemberRole::Member]);
    }
    $group = SpaceGroup::factory()->create(['community_id' => $c['community']->id]);
    $space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => $visibility]);
    if ($visibility === 'groups') {
        $buyers = AccessGroup::query()->create(['community_id' => $c['community']->id, 'name' => 'Buyers']);
        $space->accessGroups()->attach($buyers);
        $buyers->members()->attach([$c['host']->id, $c['inGroup']->id]);
    }
    $factory = Event::factory()->inSpace($space)->hostedBy($c['host']);
    $event = match ($state) {
        'draft' => $factory->draft()->create(),
        'cancelled' => $factory->cancelled()->create(),
        'past' => $factory->past()->create(),
        default => $factory->create(),
    };

    return [$c, $event];
}

test('the comments of an event per role, per visibility of its space and per state (#1516)', function (string $visibility, string $state, array $comment) {
    [$c, $event] = policyEventOf($this->c, $visibility, $state);

    foreach (POLICY_EVENT_READERS as $role) {
        $allowed = $c[$role] === null ? Gate::forUser(null)->allows('comment', $event) : Gate::forUser($c[$role])->allows('comment', $event);
        expect($allowed)->toBe(in_array($role, $comment, true), "{$role}, comment");
    }
})->with([
    'a public space: every member, never an outsider or a visitor' => ['public', 'published', ['owner', 'admin', 'moderator', 'host', 'member', 'inGroup']],
    'a members space' => ['members', 'published', ['owner', 'admin', 'moderator', 'host', 'member', 'inGroup']],
    'a groups space: the members of the group, the owner and the admins' => ['groups', 'published', ['owner', 'admin', 'host', 'inGroup']],
    'a cancelled event takes comments' => ['public', 'cancelled', ['owner', 'admin', 'moderator', 'host', 'member', 'inGroup']],
    'an event that ended takes comments' => ['members', 'past', ['owner', 'admin', 'moderator', 'host', 'member', 'inGroup']],
    'a draft takes none, also from its host, the owner and the admins' => ['members', 'draft', []],
    'a draft of a public space' => ['public', 'draft', []],
]);

test('the replay link of an event per role, per visibility of its space, per state and per answer (#1516)', function (string $visibility, string $state, string $where, array $replay) {
    [$c, $event] = policyEventOf($this->c, $visibility, $state);
    $event->forceFill(['location_visibility' => $where])->save();
    // The member answered Replay only: on a `going` event the meeting address is not for them, the replay is
    if ($state !== 'draft') {
        EventAnswer::factory()->to($event)->of($c['member'])->replay()->create();
    }
    $event = Event::query()->findOrFail($event->id);

    foreach (POLICY_EVENT_READERS as $role) {
        $allowed = $c[$role] === null ? Gate::forUser(null)->allows('viewReplay', $event) : Gate::forUser($c[$role])->allows('viewReplay', $event);
        expect($allowed)->toBe(in_array($role, $replay, true), "{$role}, viewReplay");
    }
})->with([
    'a public space, members: every member, never an outsider or a visitor' => ['public', 'past', 'members', ['owner', 'admin', 'moderator', 'host', 'member', 'inGroup']],
    'a public space, going: every member too, whatever the answer' => ['public', 'past', 'going', ['owner', 'admin', 'moderator', 'host', 'member', 'inGroup']],
    'a members space' => ['members', 'published', 'going', ['owner', 'admin', 'moderator', 'host', 'member', 'inGroup']],
    'a groups space: the members of the group, the owner and the admins' => ['groups', 'past', 'members', ['owner', 'admin', 'host', 'inGroup']],
    'a cancelled event' => ['public', 'cancelled', 'going', ['owner', 'admin', 'moderator', 'host', 'member', 'inGroup']],
    'a draft: who may see it' => ['public', 'draft', 'members', ['owner', 'admin', 'host']],
]);

test('a host who lost the access group, and a host who left, read no answer and do not answer', function () {
    $host = User::factory()->withUserProfile()->create();
    $this->c['community']->users()->attach($host, ['role' => KnowiiCommunityMemberRole::Member]);
    $space = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $buyers = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Buyers']);
    $space->accessGroups()->attach($buyers);
    $buyers->members()->attach($host);
    $event = Event::factory()->inSpace($space)->hostedBy($host)->create();
    expect(Gate::forUser($host)->allows('viewAnswers', $event))->toBeTrue()->and(Gate::forUser($host)->allows('answer', $event))->toBeTrue();

    $buyers->members()->detach($host);
    $host = User::findOrFail($host->id);
    expect(Gate::forUser($host)->allows('viewAnswers', $event))->toBeFalse()->and(Gate::forUser($host)->allows('answer', $event))->toBeFalse();

    $buyers->members()->attach($host);
    $this->c['community']->removeUser($host);
    $host = User::findOrFail($host->id);
    expect(Gate::forUser($host)->allows('viewAnswers', $event))->toBeFalse()->and(Gate::forUser($host)->allows('answer', $event))->toBeFalse();
});

test('the address of a "going" event: also the members who answered Yes, never Replay only or No (#1515)', function () {
    $events = policyEvents($this->c);
    $going = $events['goingEvent'];
    $address = fn (): bool => Gate::forUser(User::findOrFail($this->c['member']->id))->allows('viewLocation', Event::query()->findOrFail($going->id));
    expect($address())->toBeFalse();

    foreach (['replay' => false, 'no' => false, 'yes' => true] as $answer => $allowed) {
        EventAnswer::query()->updateOrCreate(['event_id' => $going->id, 'user_id' => $this->c['member']->id], ['community_id' => $going->community_id, 'answer' => $answer]);
        expect($address())->toBe($allowed, $answer);
    }
    // A Yes to another event gives nothing here, and the Yes of another member gives nothing to this one
    EventAnswer::query()->where('user_id', $this->c['member']->id)->delete();
    EventAnswer::factory()->to($events['publicEvent'])->of($this->c['member'])->yes()->create();
    EventAnswer::factory()->to($going)->of($this->c['admin'])->yes()->create();
    expect($address())->toBeFalse();

    // The answer counts also when the event is cancelled or over
    EventAnswer::factory()->to($going)->of($this->c['member'])->yes()->create();
    $going->update(['state' => 'cancelled']);
    expect($address())->toBeTrue();
    $going->update(['state' => 'published', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHour()]);
    expect($address())->toBeTrue();
    // Not for a person who left: the answers go with the membership, and a person who is not a member never reads it
    $this->c['community']->removeUser($this->c['member']);
    expect($address())->toBeFalse();
});

test('comment abilities per role', function (string $ability, array $allowed) {
    expectAllowedRoles($this->c, $ability, [$this->comment], $allowed);
})->with([
    'view' => ['view', POLICY_MEMBERS],
    'react' => ['react', POLICY_MEMBERS],
    'edit: the author only' => ['update', ['member']],
    'delete: the author and the moderators' => ['delete', ['owner', 'admin', 'moderator', 'member']],
]);

test('an author who left the community can no longer edit or delete their post and comment', function () {
    $this->c['community']->users()->detach($this->c['member']);
    $author = User::findOrFail($this->c['member']->id);

    expect(Gate::forUser($author)->allows('update', $this->post))->toBeFalse()
        ->and(Gate::forUser($author)->allows('delete', $this->post))->toBeFalse()
        ->and(Gate::forUser($author)->allows('update', $this->comment))->toBeFalse()
        ->and(Gate::forUser($author)->allows('delete', $this->comment))->toBeFalse();
});

test('a role change takes effect on the next check', function () {
    $member = $this->c['member'];
    expect(Gate::forUser($member)->allows('moderate', $this->membersSpace))->toBeFalse();

    $this->c['community']->users()->updateExistingPivot($member->id, ['role' => KnowiiCommunityMemberRole::Moderator]);
    $member = User::findOrFail($member->id);

    expect(Gate::forUser($member)->allows('moderate', $this->membersSpace))->toBeTrue()
        ->and(Gate::forUser($member)->allows('manageSpaces', $this->c['community']))->toBeFalse();
});

/*
 * The moderator rights of ADR 0017 (#1240): each right switches one moderator ability. Owner and admins always have
 * it, members never.
 */
test('each moderator right switches the matching ability of the moderators', function (KnowiiModeratorPermission $permission, string $ability, string $subject) {
    foreach ([true, false] as $enabled) {
        $community = $this->c['community']->fresh();
        $community->update(['moderator_permissions' => [...$community->moderatorPermissions(), $permission->value => $enabled]]);

        $target = match ($subject) {
            'community' => $community,
            'space' => $this->membersSpace->fresh(),
            'post' => $this->post->fresh(),
        };

        expectAllowedRoles($this->c, $ability, [$target], $enabled ? POLICY_MODERATORS : ['owner', 'admin']);
    }
})->with([
    'manage spaces' => [KnowiiModeratorPermission::ManageSpaces, 'manageSpaces', 'community'],
    'manage members: add' => [KnowiiModeratorPermission::ManageMembers, 'addCommunityMember', 'community'],
    'manage members: change roles' => [KnowiiModeratorPermission::ManageMembers, 'updateCommunityMember', 'community'],
    'manage members: remove' => [KnowiiModeratorPermission::ManageMembers, 'removeCommunityMember', 'community'],
    'pin posts' => [KnowiiModeratorPermission::PinPosts, 'pin', 'post'],
    'delete content' => [KnowiiModeratorPermission::DeleteContent, 'moderate', 'space'],
]);

test('without the "delete content" right, moderators no longer delete the posts and comments of others', function () {
    $this->c['community']->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'delete_content' => false]]);

    expectAllowedRoles($this->c, 'delete', [$this->post->fresh()], ['owner', 'admin', 'member']);
    expectAllowedRoles($this->c, 'delete', [$this->comment->fresh()], ['owner', 'admin', 'member']);
});

test('moderators keep posting where only moderators post, whatever their rights', function () {
    $this->c['community']->update(['moderator_permissions' => array_map(fn () => false, KnowiiModeratorPermission::defaults())]);

    expectAllowedRoles($this->c, 'contribute', [$this->publicModeratedSpace->fresh()], POLICY_MODERATORS);
});

test('a community built in memory has the default moderator rights (rules, Data 7)', function () {
    expect((new Community)->moderatorPermissions())->toBe(KnowiiModeratorPermission::defaults());
});
