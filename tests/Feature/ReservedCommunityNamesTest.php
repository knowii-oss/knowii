<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use App\Rules\AvailableCommunityName;
use App\Support\ReservedCommunityNames;
use Illuminate\Testing\TestResponse;

/*
 * Reserved community names (#1340, ADR 0036).
 */

beforeEach(function () {
    config([
        'communities.extra_reserved_names' => ['Acme Academy', 'dSebastien'],
        'communities.reserved_names_owners' => ['root@example.com'],
    ]);
    $this->user = User::factory()->withUserProfile()->create();
    $this->administrator = User::factory()->withUserProfile()->create(['email' => 'Root@Example.com']);
});

function reservedNameCheck(User $user, string $name, ?Community $community = null): TestResponse
{
    $body = $community === null ? ['name' => $name] : ['name' => $name, 'community' => $community->cuid];

    return test()->actingAs($user)->postJson('/api/v1/'.ApiRoutes::COMMUNITIES_IS_NAME_AVAILABLE, $body);
}

function reservedNameCreate(User $user, string $name, string $visibility = 'private'): TestResponse
{
    return test()->actingAs($user)->postJson('/api/v1/'.ApiRoutes::COMMUNITIES, ['name' => $name, 'description' => '', 'visibility' => $visibility]);
}

test('a reserved name is reserved whatever the case and by the address that it gives (#1340)', function (string $name, bool $reserved) {
    expect(ReservedCommunityNames::isReserved($name))->toBe($reserved);
})->with([
    'built-in name' => ['Support', true],
    'other case' => ['SUPPORT', true],
    'spaces around' => ['  support ', true],
    'the name of the platform' => ['Knowii', true],
    'same address' => ['Knowii-Community', true],
    'name of the instance' => ['Acme Academy', true],
    'name of the instance, as an address' => ['acme-academy', true],
    'name of the instance, other case' => ['DSEBASTIEN', true],
    'a longer name is free' => ['Support for gardeners', false],
    'a name that only starts like a reserved one' => ['Knowii Fans', false],
    'any other name' => ['Knowledge Builders', false],
    'empty' => ['', false],
]);

test('the name check answers "taken" for a reserved name, like for a name in use (#1340)', function () {
    reservedNameCheck($this->user, 'Support')
        ->assertOk()
        ->assertExactJson([
            'category' => 'business', 'type' => KnowiiApiResponseType::Success->value, 'message' => 'Community name availability checked',
            'data' => ['available' => false],
        ]);
    reservedNameCheck($this->user, 'acme academy')->assertOk()->assertJsonPath('data.available', false);
    reservedNameCheck($this->user, 'Knowledge Builders')->assertOk()->assertJsonPath('data.available', true);
});

test('nobody creates a shared community with a reserved name, except the administrators of the instance (#1340)', function () {
    reservedNameCreate($this->user, 'Knowii Community')
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value)
        ->assertJsonPath('errors.name.0', AvailableCommunityName::MESSAGE);
    reservedNameCreate($this->user, 'acme-academy', 'public')->assertStatus(400);
    expect(Community::query()->where('visibility', '!=', KnowiiCommunityVisibility::Personal)->count())->toBe(0);

    reservedNameCheck($this->administrator, 'Knowii Community')->assertOk()->assertJsonPath('data.available', true);
    reservedNameCreate($this->administrator, 'Knowii Community')->assertCreated()->assertJsonPath('data.slug', 'knowii-community');

    // Once the real community exists, the name is taken for the administrators too (ADR 0033)
    reservedNameCheck($this->administrator, 'knowii community')->assertOk()->assertJsonPath('data.available', false);
});

test('an administrator with an unverified address cannot use a reserved name (#1340)', function () {
    $this->administrator->forceFill(['email_verified_at' => null])->save();

    expect(ReservedCommunityNames::canBeUsedBy($this->administrator))->toBeFalse();
    expect(AvailableCommunityName::isAvailable('Support', null, $this->administrator))->toBeFalse();
    expect(ReservedCommunityNames::canBeUsedBy(null))->toBeFalse();
    expect(ReservedCommunityNames::canBeUsedBy($this->user))->toBeFalse();
});

test('a community cannot be renamed to a reserved name, and keeps a name that became reserved (#1340)', function () {
    $community = Community::factory()->create(['owner_id' => $this->user->id, 'visibility' => KnowiiCommunityVisibility::Private, 'name' => 'Gardeners', 'slug' => 'gardeners']);
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);

    $this->actingAs($this->user)->patchJson($path, ['name' => 'Official'])
        ->assertStatus(400)
        ->assertJsonPath('errors.name.0', AvailableCommunityName::MESSAGE);
    reservedNameCheck($this->user, 'Official', $community)->assertOk()->assertJsonPath('data.available', false);
    expect($community->fresh()->name)->toBe('Gardeners');

    // The instance reserves the name later: the community keeps it, and its owner can still save its settings
    config(['communities.extra_reserved_names' => ['Gardeners']]);
    reservedNameCheck($this->user, 'Gardeners', $community)->assertOk()->assertJsonPath('data.available', true);
    $this->actingAs($this->user)->patchJson($path, ['name' => 'Gardeners', 'description' => 'Still ours'])->assertOk();
    reservedNameCheck($this->user, 'gardeners')->assertOk()->assertJsonPath('data.available', false);
});

test('a personal community never checks the reserved names (#1340)', function () {
    reservedNameCreate($this->user, 'Support', 'personal')->assertCreated();
});

test('the lists come from the configuration and ignore empty values (#1340)', function () {
    config(['communities.extra_reserved_names' => ['', '  ', 'Only This'], 'communities.reserved_names' => ['Base']]);

    expect(ReservedCommunityNames::names())->toBe(['Base', 'Only This']);
    expect(ReservedCommunityNames::isReserved('Support'))->toBeFalse();

    config(['communities.reserved_names' => null, 'communities.extra_reserved_names' => 'not a list']);
    expect(ReservedCommunityNames::names())->toBe([]);
});
