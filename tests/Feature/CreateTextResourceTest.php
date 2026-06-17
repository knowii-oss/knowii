<?php

use App\Actions\Resources\CreateTextResource;
use App\Constants;
use App\Enums\KnowiiResourceLevel;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\CommunityResourceCollection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Create an owner with a community and a resource collection in it.
 *
 * @return array{0: User, 1: Community, 2: CommunityResourceCollection}
 */
function makeOwnerWithCollection(): array
{
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $community = $owner->ownedCommunities()->first();
    $collection = $community->communityResourceCollections()->create([
        'name' => 'Test Collection',
        'description' => 'A collection',
    ]);

    return [$owner, $community, $collection];
}

test('text resources can be created via the creator', function () {
    [$owner, $community, $collection] = makeOwnerWithCollection();

    $input = [
        'name' => 'Some Article',
        'description' => 'A great read',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    (new CreateTextResource)->create($owner, $community, $collection, $input);
})->skip('Requires faking the Browserless/Guzzle network calls (FetchUrl is final and not injectable).');

test('text resource creation is rejected if the user is not authorized', function () {
    [, $community, $collection] = makeOwnerWithCollection();

    $otherUser = User::factory()->withUserProfile()->withPersonalCommunity()->create();

    $input = [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    $this->expectException(AuthorizationException::class);

    (new CreateTextResource)->create($otherUser, $community, $collection, $input);
});

test('text resource creation is rejected if the name is missing', function () {
    [$owner, $community, $collection] = makeOwnerWithCollection();

    $input = [
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    $this->expectException(ValidationException::class);

    (new CreateTextResource)->create($owner, $community, $collection, $input);
});

test('text resource creation is rejected if the url is missing', function () {
    [$owner, $community, $collection] = makeOwnerWithCollection();

    $input = [
        'name' => 'Some Article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    $this->expectException(ValidationException::class);

    (new CreateTextResource)->create($owner, $community, $collection, $input);
});

test('text resource creation is rejected if the url is invalid', function () {
    [$owner, $community, $collection] = makeOwnerWithCollection();

    $input = [
        'name' => 'Some Article',
        'url' => 'not-a-valid-url',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    $this->expectException(ValidationException::class);

    (new CreateTextResource)->create($owner, $community, $collection, $input);
});

test('text resource creation is rejected if the level is missing', function () {
    [$owner, $community, $collection] = makeOwnerWithCollection();

    $input = [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
    ];

    $this->expectException(ValidationException::class);

    (new CreateTextResource)->create($owner, $community, $collection, $input);
});

test('text resource creation is rejected if the level is invalid', function () {
    [$owner, $community, $collection] = makeOwnerWithCollection();

    $input = [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => 'super-hard',
    ];

    $this->expectException(ValidationException::class);

    (new CreateTextResource)->create($owner, $community, $collection, $input);
});

test('text resource creation is rejected if the name is too long', function () {
    [$owner, $community, $collection] = makeOwnerWithCollection();

    $input = [
        'name' => str_repeat('a', Constants::MAX_LENGTH_COMMUNITY_RESOURCE_NAME + 1),
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    $this->expectException(ValidationException::class);

    (new CreateTextResource)->create($owner, $community, $collection, $input);
});

test('text resource creation is rejected for video and streaming platforms', function () {
    [$owner, $community, $collection] = makeOwnerWithCollection();

    $input = [
        'name' => 'Some Video',
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    $this->expectException(BusinessException::class);

    (new CreateTextResource)->create($owner, $community, $collection, $input);
});
