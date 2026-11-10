<?php

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;

/**
 * GET /api/v1/communities: the communities of the current member (#828).
 */
beforeEach(function () {
    $this->me = User::factory()->withUserProfile()->create();
    $this->owned = Community::factory()->create(['owner_id' => $this->me->id, 'name' => 'Zettel Lovers', 'visibility' => KnowiiCommunityVisibility::Private]);
    $this->joined = Community::factory()->create(['name' => 'Knowledge Builders', 'visibility' => KnowiiCommunityVisibility::Public]);
    $this->joined->users()->attach($this->me, ['role' => KnowiiCommunityMemberRole::Member]);
    $this->other = Community::factory()->create(['name' => 'Knowledge Elsewhere', 'visibility' => KnowiiCommunityVisibility::Public]);
});

test('a member lists the communities they own and the ones they belong to, by name, never the others', function () {
    $response = $this->actingAs($this->me)->getJson('/api/v1/communities')->assertOk();

    expect($response->json('data.*.cuid'))->toBe([$this->joined->cuid, $this->owned->cuid])
        ->and($response->json('metadata.pagination.totalCount'))->toBe(2);
});

test('the list pages with limit and offset, and filters on visibility and name', function () {
    $this->actingAs($this->me);

    $this->getJson('/api/v1/communities?limit=1&offset=1')->assertOk()->assertJsonPath('data.0.cuid', $this->owned->cuid)->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/communities?visibility=public')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.cuid', $this->joined->cuid);
    $this->getJson('/api/v1/communities?query=zettel')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.cuid', $this->owned->cuid);
    $this->getJson('/api/v1/communities?query=%25')->assertOk()->assertJsonCount(0, 'data');
});

test('the list needs a signed-in member, and communities:read for a token', function () {
    $this->getJson('/api/v1/communities')->assertUnauthorized();

    $token = $this->me->createToken('posts only', ['posts:read'])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/communities')->assertForbidden();

    $this->app['auth']->forgetGuards();
    $reader = $this->me->createToken('reader', ['communities:read'])->plainTextToken;
    $this->withToken($reader)->getJson('/api/v1/communities')->assertOk()->assertJsonCount(2, 'data');
});
