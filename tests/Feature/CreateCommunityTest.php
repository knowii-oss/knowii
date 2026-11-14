<?php

use App\Actions\Communities\CreateCommunity;
use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\User;
use App\Support\LegalPages;
use Illuminate\Validation\ValidationException;

test('communities can be created via the creator', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);

    $input = [
        'name' => 'Test Community',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $creator = new CreateCommunity;

    $creator->create($user, $input);

    expect($user->fresh()->ownedCommunities)->toHaveCount(2);
    expect($user->ownedCommunities()->latest('id')->first()->name)->toEqual('Test Community');
    expect($user->ownedCommunities()->latest('id')->first()->slug)->toEqual('test-community');
    expect($user->ownedCommunities()->latest('id')->first()->description)->toEqual('Awesome community');
    expect($user->ownedCommunities()->latest('id')->first()->visibility)->toEqual(KnowiiCommunityVisibility::Public);
});

test('creation is rejected by the community creator if the name is too short', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);

    $input = [
        'name' => 'a',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $this->expectException(ValidationException::class);

    $creator = new CreateCommunity;
    $creator->create($user, $input);
});

test('creation is rejected by the community creator if the name is too long', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);

    $input = [
        'name' => str_repeat('a', Constants::MAX_LENGTH_COMMUNITY_NAME + 1),
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $this->expectException(ValidationException::class);

    $creator = new CreateCommunity;
    $creator->create($user, $input);
});

test('creation is rejected by the community creator if the name contains forbidden characters', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);

    $input = [
        'name' => 'foo ##!}{',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $this->expectException(ValidationException::class);

    $creator = new CreateCommunity;
    $creator->create($user, $input);
});

test('creation is rejected by the community creator if the description is too long', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);

    $input = [
        'name' => 'foo',
        'description' => str_repeat('a', Constants::MAX_LENGTH_COMMUNITY_DESCRIPTION + 1),
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $this->expectException(ValidationException::class);

    $creator = new CreateCommunity;
    $creator->create($user, $input);
});

test('creation is rejected by the community creator if the name is missing', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);

    $input = [
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $this->expectException(ValidationException::class);

    $creator = new CreateCommunity;
    $creator->create($user, $input);
});

test('creation is rejected by the community creator if the name is empty', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);

    $input = [
        'name' => '   ',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $this->expectException(ValidationException::class);

    $creator = new CreateCommunity;
    $creator->create($user, $input);
});

test('creation is rejected by the community creator if the name is not long enough', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);

    $input = [
        'name' => 'a',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $this->expectException(ValidationException::class);

    $creator = new CreateCommunity;
    $creator->create($user, $input);
});

test('creation is rejected by the community creator if the visibility is not provided', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);

    $input = [
        'name' => 'a',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $this->expectException(ValidationException::class);

    $creator = new CreateCommunity;
    $creator->create($user, $input);
});

test('creation is rejected by the community creator if the provided visibility does not exist', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);

    $input = [
        'name' => 'a',
        'description' => 'Awesome community',
        'visibility' => 'foobar',
    ];

    $this->expectException(ValidationException::class);

    $creator = new CreateCommunity;
    $creator->create($user, $input);
});

test('community slug generation avoids duplicate slugs', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $input1 = [
        'name' => 'Test',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    // A personal community may take the name of a shared one (ADR 0033), and still gets its own slug
    $input2 = [
        'name' => 'Test',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Personal->value,
    ];

    $creator = new CreateCommunity;

    $community1 = $creator->create($user, $input1);
    $community2 = $creator->create($user, $input2);

    expect($user->fresh()->ownedCommunities)->toHaveCount(3);
    expect($community1->getAttribute('slug'))->toEqual('test');
    expect($community2->getAttribute('slug'))->toEqual('test-2');

});

test('a community can be created through the API without a description (#1331)', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create())
        ->postJson('/api/v1/communities', ['name' => 'Without Description', 'visibility' => KnowiiCommunityVisibility::Public->value])
        ->assertCreated()
        ->assertJsonPath('data.description', '');
});

test('a shared community records the acceptance of the DPA and its version (#1353)', function () {
    $user = User::factory()->withUserProfile()->create();

    $community = (new CreateCommunity)->create($user, ['name' => 'Shared Garden', 'visibility' => KnowiiCommunityVisibility::Private->value])->fresh();

    expect($community->dpa_accepted_at)->not->toBeNull()
        ->and($community->dpa_accepted_at->isToday())->toBeTrue()
        ->and($community->dpa_version->toDateString())->toBe(LegalPages::updated('dpa'));
});

test('a personal community records no acceptance, nobody else has data in it (#1353)', function () {
    $user = User::factory()->withUserProfile()->create();

    $community = (new CreateCommunity)->create($user, ['name' => 'My own place', 'visibility' => KnowiiCommunityVisibility::Personal->value])->fresh();

    expect($community->dpa_accepted_at)->toBeNull()
        ->and($community->dpa_version)->toBeNull();
});
