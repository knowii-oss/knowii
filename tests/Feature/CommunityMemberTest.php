<?php

use App\Enums\KnowiiCommunityMemberRole;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;

test('attaching a member stores the role and generates a cuid', function () {
    $owner = User::factory()->withUserProfile()->create();
    $community = Community::factory()->create(['owner_id' => $owner->id]);
    $user = User::factory()->withUserProfile()->create();

    $community->users()->attach($user, ['role' => KnowiiCommunityMemberRole::Moderator]);

    $membership = $community->getMemberById($user->id);
    expect($membership)->toBeInstanceOf(CommunityMember::class)
        ->and($membership?->role)->toBe(KnowiiCommunityMemberRole::Moderator)
        ->and($membership?->cuid)->toBeString()->not->toBeEmpty();
});

test('the role of a member can be updated through the relation', function () {
    ['community' => $community, 'member' => $member] = communityWithMembers();

    $community->users()->updateExistingPivot($member->id, ['role' => KnowiiCommunityMemberRole::Admin]);

    expect($community->getMemberById($member->id)?->role)->toBe(KnowiiCommunityMemberRole::Admin);
});

test('only the cuid and the role are mass assignable', function () {
    expect((new CommunityMember)->getFillable())->toBe(['cuid', 'role']);
});

test('the community and the user of a membership cannot be mass assigned', function () {
    ['community' => $community, 'member' => $member] = communityWithMembers();
    $other = Community::factory()->create(['owner_id' => $member->id]);

    $community->users()->updateExistingPivot($member->id, ['community_id' => $other->id]);
})->throws(MassAssignmentException::class);
