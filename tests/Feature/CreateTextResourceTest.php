<?php

use App\Actions\Resources\CreateTextResource;
use App\Constants;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Create an owner with a community and a resources space in it (ADR 0015).
 *
 * @return array{0: User, 1: Space}
 */
function makeOwnerWithResourcesSpace(KnowiiSpacePostingPolicy $postingPolicy = KnowiiSpacePostingPolicy::Moderators, KnowiiSpaceType $type = KnowiiSpaceType::Resources): array
{
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $community = $owner->ownedCommunities()->first();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $community->id]))
        ->create(['type' => $type, 'posting_policy' => $postingPolicy]);

    return [$owner, $space];
}

const ARTICLE_HTML = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
    .'<title>Some Article Title</title><meta name="description" content="A great read about testing">'
    .'</head><body><article><h1>Some Article Title</h1>'
    .'<p>This is a sufficiently long paragraph of readable content so that the parser has '
    .'something meaningful to extract. It discusses software testing and quality in general terms, '
    .'with enough words to be considered an article rather than boilerplate.</p>'
    .'</article></body></html>';

test('text resources can be created via the creator', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        .'<title>Some Article Title</title><meta name="description" content="A great read about testing">'
        .'</head><body><article><h1>Some Article Title</h1>'
        .'<p>This is a sufficiently long paragraph of readable content so that the parser has '
        .'something meaningful to extract. It discusses software testing and quality in general terms, '
        .'with enough words to be considered an article rather than boilerplate.</p>'
        .'</article></body></html>';

    $action = new CreateTextResource;
    $action->setHttpHandlerStack(fakeTextResourceFetchHandler($html));

    $resource = $action->create($owner, $space, [
        'name' => 'Some Article',
        'description' => 'A great read',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);

    expect($resource->name)->toBe('Some Article');
    expect($resource->resource->url)->toBe('https://example.com/some-article');
    expect($space->fresh()->resources)->toHaveCount(1);
    expect($resource->space_id)->toBe($space->id);
    expect($resource->community_id)->toBe($space->community_id);
});

test('text resource creation is rejected if the user is not authorized', function () {
    [, $space] = makeOwnerWithResourcesSpace();

    $otherUser = User::factory()->withUserProfile()->withPersonalCommunity()->create();

    $input = [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    $this->expectException(AuthorizationException::class);

    (new CreateTextResource)->create($otherUser, $space, $input);
});

test('without a name, the resource takes the title of the page (#1062)', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();

    $action = new CreateTextResource;
    $action->setHttpHandlerStack(fakeTextResourceFetchHandler(ARTICLE_HTML));

    $resource = $action->create($owner, $space, [
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);

    expect($resource->name)->toBe('Some Article Title');
});

test('without a title in the page, the resource takes a title made from the URL (#1062)', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();

    $action = new CreateTextResource;
    $action->setHttpHandlerStack(fakeTextResourceFetchHandler('<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"></head><body><p>Text</p></body></html>'));

    $resource = $action->create($owner, $space, [
        'name' => '',
        'url' => 'https://example.com/blog/smart-notes_guide',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);

    expect($resource->name)->toBe('Smart Notes Guide');
});

test('without a name and without any title, the resource is refused (#1062)', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();

    $action = new CreateTextResource;
    $action->setHttpHandlerStack(fakeTextResourceFetchHandler('<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"></head><body><p>Text</p></body></html>'));

    $action->create($owner, $space, [
        'url' => 'https://example.com/',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);
})->throws(BusinessException::class, 'Could not find the title of the page. Give the resource a name');

test('a long page title is shortened to the maximum name length (#1062)', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();
    $title = str_repeat('Long title ', 10);

    $action = new CreateTextResource;
    $action->setHttpHandlerStack(fakeTextResourceFetchHandler('<!DOCTYPE html><html lang="en"><head><title>'.$title.'</title></head><body><p>Text</p></body></html>'));

    $resource = $action->create($owner, $space, [
        'url' => 'https://example.com/long',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);

    expect(mb_strlen($resource->name))->toBe(Constants::MAX_LENGTH_COMMUNITY_RESOURCE_NAME);
});

test('text resource creation is rejected if the url is missing', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();

    $input = [
        'name' => 'Some Article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    $this->expectException(ValidationException::class);

    (new CreateTextResource)->create($owner, $space, $input);
});

test('text resource creation is rejected if the url is invalid', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();

    $input = [
        'name' => 'Some Article',
        'url' => 'not-a-valid-url',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    $this->expectException(ValidationException::class);

    (new CreateTextResource)->create($owner, $space, $input);
});

test('text resource creation is rejected if the level is missing', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();

    $input = [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
    ];

    $this->expectException(ValidationException::class);

    (new CreateTextResource)->create($owner, $space, $input);
});

test('text resource creation is rejected if the level is invalid', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();

    $input = [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => 'super-hard',
    ];

    $this->expectException(ValidationException::class);

    (new CreateTextResource)->create($owner, $space, $input);
});

test('text resource creation is rejected if the name is too long', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();

    $input = [
        'name' => str_repeat('a', Constants::MAX_LENGTH_COMMUNITY_RESOURCE_NAME + 1),
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    $this->expectException(ValidationException::class);

    (new CreateTextResource)->create($owner, $space, $input);
});

test('text resource creation is rejected for video and streaming platforms', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();

    $input = [
        'name' => 'Some Video',
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    $this->expectException(BusinessException::class);

    (new CreateTextResource)->create($owner, $space, $input);
});

test('the keywords of the page are stored on the resource', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Notes</title>'
        .'<meta name="keywords" content="PKM, Smart Notes"><meta property="article:tag" content="Zettelkasten">'
        .'</head><body><article><h1>Notes</h1>'
        .'<p>This is a sufficiently long paragraph of readable content so that the parser has '
        .'something meaningful to extract. It discusses note taking and knowledge management, '
        .'with enough words to be considered an article rather than boilerplate.</p>'
        .'</article></body></html>';

    $action = new CreateTextResource;
    $action->setHttpHandlerStack(fakeTextResourceFetchHandler($html));

    $resource = $action->create($owner, $space, [
        'name' => 'Notes',
        'url' => 'https://example.com/notes',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);

    expect($resource->resource->fresh()->keywords)->toBe(['pkm', 'smart notes', 'zettelkasten']);
});

test('resources can only be added to resources spaces', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace(type: KnowiiSpaceType::Discussion);

    (new CreateTextResource)->create($owner, $space, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);
})->throws(BusinessException::class);

test('the posting policy of the space decides who adds resources', function (KnowiiSpacePostingPolicy $policy, array $allowed) {
    $c = communityWithMembers();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Resources, 'posting_policy' => $policy]);

    foreach (['owner', 'admin', 'moderator', 'member', 'outsider'] as $role) {
        expect($c[$role]->can('contribute', $space))->toBe(in_array($role, $allowed, true), "{$role} with {$policy->value}");
    }
})->with([
    'moderators' => [KnowiiSpacePostingPolicy::Moderators, ['owner', 'admin', 'moderator']],
    'members' => [KnowiiSpacePostingPolicy::Members, ['owner', 'admin', 'moderator', 'member']],
]);

test('a member adds a resource to a space where members can post', function () {
    $c = communityWithMembers();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Resources, 'posting_policy' => KnowiiSpacePostingPolicy::Members]);

    $action = new CreateTextResource;
    $action->setHttpHandlerStack(fakeTextResourceFetchHandler(ARTICLE_HTML));

    $resource = $action->create($c['member'], $space, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);

    expect($resource->curator_id)->toBe($c['member']->profile->id);
});

test('a member cannot add a resource to a space where only moderators post', function () {
    $c = communityWithMembers();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Resources, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);

    (new CreateTextResource)->create($c['member'], $space, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);
})->throws(AuthorizationException::class);

test('the same URL cannot be added twice to a space', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();
    $input = [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ];

    $action = new CreateTextResource;
    $action->setHttpHandlerStack(fakeTextResourceFetchHandler(ARTICLE_HTML));
    $action->create($owner, $space, $input);

    $action->setHttpHandlerStack(fakeTextResourceFetchHandler(ARTICLE_HTML));
    $action->create($owner, $space, $input);
})->throws(BusinessException::class, 'This resource is already in this space');

test('when the page cannot be fetched, the resource takes a title made from the URL (#1062)', function () {
    [$owner, $space] = makeOwnerWithResourcesSpace();

    $action = new CreateTextResource;
    // Browserless fails: the page content request gets a 500
    $action->setHttpHandlerStack(HandlerStack::create(new MockHandler([
        new Response(200, [], ''),
        new Response(200, [], ''),
        new Response(500, [], ''),
    ])));

    $resource = $action->create($owner, $space, [
        'url' => 'https://example.com/pick-a-tool-and-stick-with-it/',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);

    expect($resource->name)->toBe('Pick A Tool And Stick With It');
});
