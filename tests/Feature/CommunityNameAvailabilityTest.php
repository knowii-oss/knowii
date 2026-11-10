<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->c = communityWithMembers();
    // The factory gives another name: set the name and the address that it gives
    $this->c['community']->update(['name' => 'Knowledge Builders', 'slug' => 'knowledge-builders']);
});

function nameCheckPath(): string
{
    return '/api/v1/'.ApiRoutes::COMMUNITIES_IS_NAME_AVAILABLE;
}

function nameCheckCreate(User $user, string $name, string $visibility = 'public'): TestResponse
{
    return test()->actingAs($user)->postJson('/api/v1/'.ApiRoutes::COMMUNITIES, ['name' => $name, 'description' => '', 'visibility' => $visibility]);
}

test('a free name is available, a name of a shared community is taken whatever the case (#1136)', function () {
    $this->actingAs($this->c['outsider'])
        ->postJson(nameCheckPath(), ['name' => 'Gardeners of Ideas'])
        ->assertOk()
        ->assertJsonPath('type', KnowiiApiResponseType::Success->value)
        ->assertJsonPath('data.available', true);

    $this->actingAs($this->c['outsider'])
        ->postJson(nameCheckPath(), ['name' => '  knowledge BUILDERS '])
        ->assertOk()
        ->assertExactJsonStructure(['category', 'type', 'message', 'data' => ['available']])
        ->assertJsonPath('data.available', false);
});

test('a name that gives the address of another shared community is taken, also after a rename (#1136)', function () {
    // "Knowledge-Builders" gives the slug of "Knowledge Builders"
    $this->actingAs($this->c['outsider'])->postJson(nameCheckPath(), ['name' => 'Knowledge-Builders'])->assertJsonPath('data.available', false);

    // The address never changes: the old name keeps pointing to the renamed community
    $slug = $this->c['community']->slug;
    $this->c['community']->update(['name' => 'Renamed Builders']);
    expect($this->c['community']->fresh()?->slug)->toBe($slug);

    $this->actingAs($this->c['outsider'])->postJson(nameCheckPath(), ['name' => 'Knowledge Builders'])->assertJsonPath('data.available', false);
});

test('personal communities never take a name, and a private community takes it without telling more (#1136)', function () {
    $personal = User::factory()->withUserProfile()->withPersonalCommunity()->create(['name' => 'Ada Lovelace']);
    $personalName = $personal->ownedCommunities()->firstOrFail()->name;

    $this->actingAs($this->c['outsider'])->postJson(nameCheckPath(), ['name' => $personalName])->assertJsonPath('data.available', true);

    // The community of beforeEach is private: the outsider learns "taken", nothing else
    $response = $this->actingAs($this->c['outsider'])->postJson(nameCheckPath(), ['name' => 'Knowledge Builders'])->assertOk();
    expect(json_encode($response->json()))->not->toContain($this->c['community']->cuid)->not->toContain($this->c['community']->slug);
});

test('the owner checks a new name for their community, which keeps its own name (#1136)', function () {
    $cuid = $this->c['community']->cuid;

    $this->actingAs($this->c['owner'])
        ->postJson(nameCheckPath(), ['name' => 'KNOWLEDGE builders', 'community' => $cuid])
        ->assertJsonPath('data.available', true);

    Community::factory()->create(['name' => 'Taken Elsewhere', 'visibility' => KnowiiCommunityVisibility::Public]);
    $this->actingAs($this->c['owner'])
        ->postJson(nameCheckPath(), ['name' => 'Taken Elsewhere', 'community' => $cuid])
        ->assertJsonPath('data.available', false);
});

test('only the owner may exclude their community from the check (#1136)', function (string $who) {
    $this->actingAs($this->c[$who])
        ->postJson(nameCheckPath(), ['name' => 'Knowledge Builders', 'community' => $this->c['community']->cuid])
        ->assertForbidden();
})->with(['admin', 'moderator', 'member', 'outsider']);

test('an unknown community gives a 404 (#1136)', function () {
    $this->actingAs($this->c['owner'])
        ->postJson(nameCheckPath(), ['name' => 'Knowledge Builders', 'community' => 'nope'])
        ->assertNotFound();
});

test('the name follows the rules of a community name (#1136)', function (mixed $name) {
    $this->actingAs($this->c['owner'])
        ->postJson(nameCheckPath(), ['name' => $name])
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value)
        ->assertJsonStructure(['errors' => ['name']]);
})->with([
    'missing' => [null],
    'too short' => ['ab'],
    'too long' => [str_repeat('a', Constants::MAX_LENGTH_COMMUNITY_NAME + 1)],
    'forbidden characters' => ['<script>'],
]);

test('the check needs a signed-in user with a verified address and a token with communities:read (#1136)', function () {
    $this->postJson(nameCheckPath(), ['name' => 'Gardeners of Ideas'])->assertUnauthorized();

    $unverified = User::factory()->withUserProfile()->unverified()->create();
    $this->actingAs($unverified)->postJson(nameCheckPath(), ['name' => 'Gardeners of Ideas'])->assertForbidden();

    Sanctum::actingAs($this->c['member'], ['posts:read']);
    $this->postJson(nameCheckPath(), ['name' => 'Gardeners of Ideas'])->assertForbidden();

    Sanctum::actingAs($this->c['member'], ['communities:read']);
    $this->postJson(nameCheckPath(), ['name' => 'Gardeners of Ideas'])->assertOk();
});

test('the check is rate limited (#1136)', function () {
    $this->actingAs($this->c['member']);
    for ($i = 0; $i < Constants::NAME_CHECKS_PER_MINUTE; $i++) {
        $this->postJson(nameCheckPath(), ['name' => 'Gardeners of Ideas'])->assertOk();
    }
    $this->postJson(nameCheckPath(), ['name' => 'Gardeners of Ideas'])->assertStatus(429);
});

test('creating a shared community with a taken name fails, a personal one does not care (#1136)', function () {
    nameCheckCreate($this->c['member'], 'knowledge builders')
        ->assertStatus(400)
        ->assertJsonPath('errors.name.0', 'The name is already taken.');
    nameCheckCreate($this->c['member'], 'Knowledge-Builders', 'private')->assertStatus(400);

    nameCheckCreate($this->c['member'], 'Knowledge Builders', 'personal')->assertCreated();
    nameCheckCreate($this->c['member'], 'Gardeners of Ideas')->assertCreated();
});

test('renaming a shared community to a taken name fails, a change of case of its own name works (#1136)', function () {
    $other = Community::factory()->create(['owner_id' => $this->c['owner']->id, 'name' => 'Second Garden', 'visibility' => KnowiiCommunityVisibility::Public]);
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $other->cuid]);

    $this->actingAs($this->c['owner'])->patchJson($path, ['name' => 'Knowledge Builders'])
        ->assertStatus(400)
        ->assertJsonPath('errors.name.0', 'The name is already taken.');

    $this->actingAs($this->c['owner'])->patchJson($path, ['name' => 'SECOND garden'])->assertOk()->assertJsonPath('data.name', 'SECOND garden');
    $this->actingAs($this->c['owner'])->patchJson($path, ['description' => 'Only the description'])->assertOk();
});

test('the database refuses two shared communities with the same name, not two personal ones (#1136)', function () {
    // A savepoint: the failed insert must not abort the transaction of the test
    expect(fn () => DB::transaction(fn () => DB::table('communities')->insert([
        'cuid' => 'dup1', 'name' => 'KNOWLEDGE BUILDERS', 'slug' => 'dup-1', 'visibility' => 'public', 'owner_id' => $this->c['owner']->id,
        'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(UniqueConstraintViolationException::class);

    foreach (['p1', 'p2'] as $cuid) {
        DB::table('communities')->insert([
            'cuid' => $cuid, 'name' => 'Ada Lovelace\'s Space', 'slug' => 'space-'.$cuid, 'visibility' => 'personal', 'owner_id' => $this->c['owner']->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    expect(Community::query()->where('visibility', 'personal')->where('name', 'Ada Lovelace\'s Space')->count())->toBe(2);
});
