<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Community;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\AccountDeletion;
use App\Support\SecondFactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

/*
 * Delete the own account through the API (#1492, ADR 0043)
 */

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $this->me = $this->c['member'];
});

function deleteOwnAccountPath(): string
{
    return ApiRoutes::path(ApiRoutes::USER);
}

function accountExists(User $user): bool
{
    return User::query()->whereKey($user->id)->exists();
}

function deletionPost(User $author): Post
{
    $space = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => test()->c['community']->id]))->create(['type' => KnowiiSpaceType::Discussion]);

    return Post::factory()->inSpace($space)->create(['author_id' => $author->id]);
}

/**
 * An account with confirmed two-factor authentication and two recovery codes.
 *
 * @return array{0: User, 1: string}
 */
function deletionUserWithTwoFactor(): array
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one', 'recovery-two'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user, $secret];
}

test('a token with the ability deletes the account: 204, and what the member wrote stays without an author', function () {
    $post = deletionPost($this->me);
    $token = $this->me->createToken('cleaner', ['account:delete'])->plainTextToken;

    $this->withToken($token)->deleteJson(deleteOwnAccountPath(), ['password' => 'password'])->assertNoContent();

    expect(accountExists($this->me))->toBeFalse()
        ->and($post->fresh()?->author_id)->toBeNull()
        ->and($post->fresh()?->deleted_at)->toBeNull();
});

test('with delete_content the posts of the member go too, the same as on the web (ADR 0043)', function () {
    $post = deletionPost($this->me);

    Sanctum::actingAs($this->me, ['account:delete']);
    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password', 'delete_content' => true])->assertNoContent();

    expect(accountExists($this->me))->toBeFalse()
        ->and(Post::query()->whereKey($post->id)->exists())->toBeFalse();
});

test('delete_content must be a boolean: the deletion is refused and nothing changes', function () {
    Sanctum::actingAs($this->me, ['account:delete']);

    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password', 'delete_content' => 'maybe'])->assertStatus(400);

    expect(accountExists($this->me))->toBeTrue();
});

test('a wrong password is a validation error on password, with the message of the web', function () {
    Sanctum::actingAs($this->me, ['account:delete']);

    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'wrong-password'])
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value)
        ->assertJsonPath('errors.password.0', 'The password is incorrect.');

    expect(accountExists($this->me))->toBeTrue();
});

test('a missing password is a validation error on password', function () {
    Sanctum::actingAs($this->me, ['account:delete']);

    $response = $this->deleteJson(deleteOwnAccountPath(), [])->assertStatus(400);

    expect($response->json('errors'))->toHaveKey('password')
        ->and(accountExists($this->me))->toBeTrue();
});

test('with two-factor authentication on, a missing code is a validation error on code', function () {
    [$user] = deletionUserWithTwoFactor();
    Sanctum::actingAs($user, ['account:delete']);

    $response = $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password'])->assertStatus(400);

    expect($response->json('errors'))->toHaveKey('code')
        ->and(accountExists($user))->toBeTrue();
});

test('with two-factor authentication on, a wrong code is a validation error on code', function () {
    [$user] = deletionUserWithTwoFactor();
    Sanctum::actingAs($user, ['account:delete']);

    $response = $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password', 'code' => '000000'])->assertStatus(400);

    expect($response->json('errors'))->toHaveKey('code')
        ->and(accountExists($user))->toBeTrue();
});

test('the password is checked before the code: a wrong password with a good code deletes nothing', function () {
    [$user, $secret] = deletionUserWithTwoFactor();
    Sanctum::actingAs($user, ['account:delete']);

    $response = $this->deleteJson(deleteOwnAccountPath(), ['password' => 'wrong-password', 'code' => (new Google2FA)->getCurrentOtp($secret)])->assertStatus(400);

    expect($response->json('errors'))->toHaveKey('password')
        ->and(accountExists($user))->toBeTrue();
});

test('with two-factor authentication on, the code of the authenticator app deletes the account', function () {
    [$user, $secret] = deletionUserWithTwoFactor();
    Sanctum::actingAs($user, ['account:delete']);

    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password', 'code' => (new Google2FA)->getCurrentOtp($secret)])->assertNoContent();

    expect(accountExists($user))->toBeFalse();
});

test('a recovery code deletes the account and is consumed', function () {
    [$user] = deletionUserWithTwoFactor();
    Sanctum::actingAs($user, ['account:delete']);

    // A wrong try does not consume anything
    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'wrong-password', 'code' => 'recovery-one'])->assertStatus(400);
    expect($user->fresh()?->recoveryCodes())->toContain('recovery-one');

    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password', 'code' => 'recovery-one'])->assertNoContent();

    expect(accountExists($user))->toBeFalse();
});

test('a recovery code that is used is replaced, so it cannot be used again', function () {
    [$user] = deletionUserWithTwoFactor();
    Sanctum::actingAs($user, ['account:delete']);

    // The delete_content value is invalid: the code is not consumed before the input is valid
    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password', 'code' => 'recovery-one', 'delete_content' => 'maybe'])->assertStatus(400);
    expect($user->fresh()?->recoveryCodes())->toContain('recovery-one');

    expect(SecondFactor::passesEither($user, 'recovery-one'))->toBeTrue()
        ->and($user->fresh()?->recoveryCodes())->not->toContain('recovery-one')
        ->and(SecondFactor::passesEither($user->fresh() ?? $user, 'recovery-one'))->toBeFalse();
});

test('a token without the ability is refused like for any missing ability', function () {
    $token = $this->me->createToken('writer', ['user:read', 'user:write', 'communities:write'])->plainTextToken;

    $this->withToken($token)->deleteJson(deleteOwnAccountPath(), ['password' => 'password'])->assertForbidden();

    expect(accountExists($this->me))->toBeTrue();
});

test('the ability is never in the default set of a new token, and is one of the choices', function () {
    expect(Jetstream::$permissions)->toContain('account:delete')
        ->and(Jetstream::$defaultPermissions)->not->toContain('account:delete');

    $this->actingAs($this->me);
    // A token created with the default set cannot delete the account
    $this->post('/user/api-tokens', ['name' => 'Default', 'permissions' => Jetstream::$defaultPermissions])->assertSessionHasNoErrors();
    $token = $this->me->tokens()->firstOrFail();
    expect($token->can('account:delete'))->toBeFalse();
});

test('a session call of the web app follows the same rule as the other routes: no ability check, the password is still needed', function () {
    $this->actingAs($this->me);

    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'wrong-password'])->assertStatus(400);
    expect(accountExists($this->me))->toBeTrue();

    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password'])->assertNoContent();
    expect(accountExists($this->me))->toBeFalse();
});

test('an account without a usable password and without a connected sign-in account gets a business error that names the operator (#1521)', function () {
    $this->me->forceFill(['password' => null])->save();
    Sanctum::actingAs($this->me, ['account:delete']);

    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password'])
        ->assertStatus(422)
        ->assertJsonPath('type', KnowiiApiResponseType::BusinessIssue->value)
        ->assertJsonPath('message', AccountDeletion::UNAVAILABLE_MESSAGE);

    expect(accountExists($this->me))->toBeTrue();
});

test('the web does not delete such an account either: no password matches, and there is no proof to make (#1521)', function () {
    $this->me->forceFill(['password' => null])->save();

    $this->actingAs($this->me)->delete('/user', ['password' => 'password'])->assertSessionHasErrors(['sign_in' => AccountDeletion::UNAVAILABLE_MESSAGE]);

    expect(accountExists($this->me))->toBeTrue();
});

test('the owner of a community deletes it with the account, with its members, like on the web', function () {
    $community = $this->c['community'];
    Sanctum::actingAs($this->c['owner'], ['account:delete']);

    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password'])->assertNoContent();

    expect(Community::query()->whereKey($community->id)->exists())->toBeFalse()
        ->and(accountExists($this->c['member']))->toBeTrue();
});

test('the memberships of the member go with the account', function () {
    $community = $this->c['community'];
    Sanctum::actingAs($this->me, ['account:delete']);

    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password'])->assertNoContent();

    expect($community->users()->whereKey($this->me->id)->exists())->toBeFalse()
        ->and(Community::query()->whereKey($community->id)->exists())->toBeTrue();
});

test('an unverified account is refused, like for every API route', function () {
    $this->me->forceFill(['email_verified_at' => null])->save();
    Sanctum::actingAs($this->me, ['account:delete']);

    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password'])->assertForbidden();

    expect(accountExists($this->me))->toBeTrue();
});

test('without a token or a session the answer is 401', function () {
    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password'])->assertUnauthorized();
});

test('the route is rate limited per account: no unlimited password guessing', function () {
    Sanctum::actingAs($this->me, ['account:delete']);

    for ($i = 0; $i < Constants::ACCOUNT_DELETIONS_PER_MINUTE; $i++) {
        $this->deleteJson(deleteOwnAccountPath(), ['password' => 'guess-'.$i])->assertStatus(400);
    }

    $this->deleteJson(deleteOwnAccountPath(), ['password' => 'password'])->assertStatus(429);
    expect(accountExists($this->me))->toBeTrue();
});

test('after the deletion every token of the account is gone, the one that was used and the others', function () {
    $used = $this->me->createToken('used', ['account:delete'])->plainTextToken;
    $this->me->createToken('other', ['user:read']);
    $this->me->createToken('another', ['*']);
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $this->me->id)->count())->toBe(3);

    $this->withToken($used)->deleteJson(deleteOwnAccountPath(), ['password' => 'password'])->assertNoContent();

    expect(DB::table('personal_access_tokens')->where('tokenable_id', $this->me->id)->count())->toBe(0)
        ->and(DB::table('personal_access_tokens')->where('tokenable_type', $this->me->getMorphClass())->where('tokenable_id', $this->me->id)->exists())->toBeFalse();
});
