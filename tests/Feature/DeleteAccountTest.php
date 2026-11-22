<?php

use App\Actions\DeleteUser;
use App\Contracts\Reactions\TogglesReactions;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\TechnicalException;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityResource;
use App\Models\InvitationImport;
use App\Models\InvitationImportRow;
use App\Models\InviteLink;
use App\Models\InviteLinkUse;
use App\Models\MemberTag;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\MemberTagAssignments;
use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Features;
use Visus\Cuid2\Cuid2;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
});

test('user accounts can be deleted', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->create());

    $this->delete('/user', [
        'password' => 'password',
    ]);

    expect($user->fresh())->toBeNull();
})->skip(function () {
    return ! Features::hasAccountDeletionFeatures();
}, 'Account deletion is not enabled.');

test('correct password must be provided before account can be deleted', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->create());

    $this->delete('/user', [
        'password' => 'wrong-password',
    ]);

    expect($user->fresh())->not->toBeNull();
})->skip(function () {
    return ! Features::hasAccountDeletionFeatures();
}, 'Account deletion is not enabled.');

test('deleting an account deletes the profile and the personal data (#1261)', function () {
    Storage::fake('public');
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $profileId = $user->profile->id;
    // The headline is personal data of the profile (#1688): a text that no other row holds
    $user->profile->forceFill(['headline' => 'HEADLINE-OF-THE-DELETED-ACCOUNT'])->save();
    $ownCommunity = $user->ownedCommunities()->firstOrFail();
    $other = Community::factory()->create();
    $other->users()->attach($user, ['role' => KnowiiCommunityMemberRole::Member]);
    CommunityInvitation::query()->forceCreate(['cuid' => (new Cuid2)->toString(), 'community_id' => Community::factory()->create()->id, 'email' => $user->email, 'role' => KnowiiCommunityMemberRole::Member]);
    $user->createToken('script');
    DB::table('sessions')->insert(['id' => 'session-of-the-user', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'hash']);
    Storage::disk('public')->put('avatars/'.$user->cuid.'/a.webp', 'image');
    $user->forceFill(['avatar_path' => 'avatars/'.$user->cuid.'/a.webp'])->save();

    $this->actingAs($user)->delete('/user', ['password' => 'password']);

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(UserProfile::query()->whereKey($profileId)->exists())->toBeFalse()
        ->and(UserProfile::query()->where('email', $user->email)->exists())->toBeFalse()
        ->and(DB::table('user_profiles')->where('headline', 'like', '%HEADLINE-OF-THE-DELETED-ACCOUNT%')->exists())->toBeFalse()
        ->and(Community::query()->whereKey($ownCommunity->id)->exists())->toBeFalse()
        ->and($other->users()->whereKey($user->id)->exists())->toBeFalse()
        ->and(CommunityInvitation::query()->where('email', $user->email)->exists())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('avatars/'.$user->cuid);
});

test('what a deleted member wrote in other communities stays, with no author (#1261)', function () {
    $c = communityWithMembers();
    $user = $c['member'];
    $space = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $c['community']->id]))->create(['type' => KnowiiSpaceType::Discussion]);
    $post = Post::factory()->inSpace($space)->create(['author_id' => $user->id]);
    $comment = Comment::factory()->onPost(Post::factory()->inSpace($space)->create())->create(['author_id' => $user->id]);
    $resource = CommunityResource::factory()->create(['curator_id' => $user->profile->id]);
    app(TogglesReactions::class)->toggle($user, $post, ['emoji' => '👍']);
    $upload = Upload::query()->forceCreate([
        'cuid' => (new Cuid2)->toString(), 'community_id' => $c['community']->id, 'user_id' => $user->id, 'disk' => 'public',
        'path' => 'uploads/a.webp', 'mime_type' => 'image/webp', 'width' => 1, 'height' => 1, 'size_bytes' => 1, 'alt' => 'An image',
    ]);

    $this->actingAs($user)->delete('/user', ['password' => 'password']);

    expect($post->fresh()?->author_id)->toBeNull()
        ->and($comment->fresh()?->author_id)->toBeNull()
        ->and($resource->fresh()?->curator_id)->toBeNull()
        ->and($upload->fresh()?->user_id)->toBeNull()
        ->and(Reaction::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('a new sign-up with the email of a deleted account gets a new profile (#1261)', function () {
    $user = User::factory()->withUserProfile(fn ($factory) => $factory->state(['bio' => 'The old bio']))->create();
    $this->actingAs($user)->delete('/user', ['password' => 'password']);

    $this->post('/register', ['name' => 'Ada Again', 'email' => $user->email, 'password' => 'password', 'password_confirmation' => 'password', 'terms' => true]);

    $profile = UserProfile::query()->where('email', $user->email)->firstOrFail();
    expect($profile->bio)->toBeNull()->and($profile->name)->toBe('Ada Again');
});

test('the logs of an account deletion keep only the cuid (#1261)', function () {
    $user = User::factory()->withUserProfile()->create();
    Log::spy();

    $this->actingAs($user)->delete('/user', ['password' => 'password']);

    Log::shouldHaveReceived('info')->with('Deleting user', ['user' => $user->cuid])->once();
    Log::shouldHaveReceived('info')->with('User deleted', ['user' => $user->cuid])->once();
});

test('a deleted account loses every member tag, and the tags stay on the other members (#1389)', function () {
    $user = User::factory()->withUserProfile()->create();
    $other = User::factory()->withUserProfile()->create();
    $community = Community::factory()->create();
    $community->users()->attach([$user->id => ['role' => 'member'], $other->id => ['role' => 'member']]);
    $shown = MemberTag::factory()->forMembers()->create(['community_id' => $community->id, 'name' => 'Founder']);
    $hidden = MemberTag::factory()->forManagers()->create(['community_id' => $community->id, 'name' => 'Onboarded']);
    MemberTagAssignments::add($shown, $user);
    MemberTagAssignments::add($hidden, $user);
    MemberTagAssignments::add($shown, $other);

    $this->actingAs($user)->delete('/user', ['password' => 'password']);

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(DB::table('member_tag_members')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($shown->members()->pluck('users.id')->all())->toBe([$other->id])
        ->and(MemberTag::query()->whereKey($hidden->id)->exists())->toBeTrue();
});

test('the deletion of an account with capitals in its address deletes the invitations of that address (#1390)', function () {
    $user = User::factory()->withUserProfile()->create(['email' => 'Grace.Hopper@Example.com']);
    $community = Community::factory()->create();
    $invitation = $community->communityInvitations()->create(['email' => 'grace.hopper@example.com', 'role' => KnowiiCommunityMemberRole::Member]);
    $other = $community->communityInvitations()->create(['email' => 'ada@example.com', 'role' => KnowiiCommunityMemberRole::Member]);

    $this->actingAs($user)->delete('/user', ['password' => 'password']);

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(CommunityInvitation::query()->whereKey($invitation->id)->exists())->toBeFalse()
        ->and(CommunityInvitation::query()->whereKey($other->id)->exists())->toBeTrue();
});

test('the deletion of an account deletes the rows of imports with its address in any case; an import that it uploaded stays without its author (#1390)', function () {
    $user = User::factory()->withUserProfile()->create(['email' => 'Grace.Hopper@Example.com']);
    $community = Community::factory()->create();
    $uploaded = InvitationImport::factory()->done()->create(['community_id' => $community->id, 'user_id' => $user->id]);
    $other = InvitationImport::factory()->done()->create(['community_id' => $community->id]);
    $mine = InvitationImportRow::factory()->create(['invitation_import_id' => $other->id, 'row_number' => 2, 'email' => 'grace.hopper@example.com']);
    $kept = InvitationImportRow::factory()->create(['invitation_import_id' => $other->id, 'row_number' => 3, 'email' => 'ada@example.com']);

    $this->actingAs($user)->delete('/user', ['password' => 'password']);

    expect(InvitationImportRow::query()->whereKey($mine->id)->exists())->toBeFalse()
        ->and(InvitationImportRow::query()->whereKey($kept->id)->exists())->toBeTrue()
        ->and($uploaded->fresh()?->user_id)->toBeNull();
});

test('a deadlock at the deletion of an owned community reaches the deletion of the account as a deadlock, so that its transaction can run again, and nothing is deleted (#1390)', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $community = $user->ownedCommunities()->firstOrFail();
    DB::beforeExecuting(static function (string $query, array $bindings, Connection $connection): void {
        if (str_starts_with($query, 'delete from "communities"')) {
            throw new QueryException($connection->getName() ?? 'pgsql', $query, $bindings, new PDOException('SQLSTATE[40P01]: Deadlock detected: 7 ERROR:  deadlock detected'));
        }
    });

    $thrown = null;
    try {
        app(DeleteUser::class)->delete($user);
    } catch (Throwable $e) {
        $thrown = $e;
    }

    // In the transaction of the test, the one of the deletion is nested: the framework gives the deadlock without a new
    // attempt. Out of a test it is the outer one, and runs again (3 attempts in all)
    expect($thrown)->toBeInstanceOf(Throwable::class)
        ->and($thrown)->not->toBeInstanceOf(TechnicalException::class)
        ->and(app(ConcurrencyErrorDetector::class)->causedByConcurrencyError($thrown))->toBeTrue()
        ->and(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and(Community::query()->whereKey($community->id)->exists())->toBeTrue();
});

test('a deleted account loses its records of the joins through invite links, and the links and their counts stay (#1391)', function () {
    $user = User::factory()->withUserProfile()->create();
    $other = User::factory()->withUserProfile()->create();
    $community = Community::factory()->create();
    $community->users()->attach([$user->id => ['role' => 'member'], $other->id => ['role' => 'member']]);
    $link = InviteLink::factory()->create(['community_id' => $community->id]);
    $link->forceFill(['uses_count' => 2])->save();
    InviteLinkUse::query()->create(['invite_link_id' => $link->id, 'user_id' => $user->id]);
    InviteLinkUse::query()->create(['invite_link_id' => $link->id, 'user_id' => $other->id]);

    $this->actingAs($user)->delete('/user', ['password' => 'password']);

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(InviteLinkUse::query()->pluck('user_id')->all())->toBe([$other->id])
        ->and($link->fresh()?->uses_count)->toBe(2);
});
