<?php

use App\Contracts\Reactions\TogglesReactions;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityResource;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use App\Models\User;
use App\Models\UserProfile;
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
