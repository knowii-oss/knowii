<?php

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiModeratorPermission;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Comment;
use App\Models\Community;
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
