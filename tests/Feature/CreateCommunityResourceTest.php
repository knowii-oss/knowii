<?php

use App\Actions\Resources\CreateCommunityResource;
use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Jobs\CaptureCommunityResource;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
});

/**
 * An owner with a community and a resources space in it (ADR 0015).
 *
 * @return array{0: User, 1: Space}
 */
function ownerWithResourcesSpace(KnowiiSpacePostingPolicy $postingPolicy = KnowiiSpacePostingPolicy::Moderators, KnowiiSpaceType $type = KnowiiSpaceType::Resources): array
{
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $owner->ownedCommunities()->first()->id]))
        ->create(['type' => $type, 'posting_policy' => $postingPolicy]);

    return [$owner, $space];
}

function addResource(User $user, Space $space, array $input): CommunityResource
{
    return (new CreateCommunityResource)->create($user, $space, $input + ['level' => KnowiiResourceLevel::Beginner->value]);
}

test('adding a link is fast: a pending resource, a queued capture, no HTTP request (ADR 0018)', function () {
    [$owner, $space] = ownerWithResourcesSpace();

    $created = addResource($owner, $space, ['name' => 'Some Article', 'description' => 'Worth it', 'url' => 'https://example.com/some-article']);

    expect($created->capture_status)->toBe(KnowiiCaptureStatus::Pending)
        ->and($created->name)->toBe('Some Article')
        ->and($created->description)->toBe('Worth it')
        ->and($created->curator_id)->toBe($owner->profile->id)
        ->and($created->resource->url)->toBe('https://example.com/some-article');
    Queue::assertPushed(CaptureCommunityResource::class, fn (CaptureCommunityResource $job) => $job->communityResource->is($created) && ! $job->nameFromPage);
    Http::assertNothingSent();
});

test('without a name, the resource takes a name from the URL until the capture gives the title (#1062)', function () {
    [$owner, $space] = ownerWithResourcesSpace();

    expect(addResource($owner, $space, ['url' => 'https://example.com/blog/smart-notes_guide/'])->name)->toBe('Smart Notes Guide')
        ->and(addResource($owner, $space, ['url' => 'https://www.example.org'])->name)->toBe('example.org');
    Queue::assertPushed(CaptureCommunityResource::class, fn (CaptureCommunityResource $job) => $job->nameFromPage);
});

test('a URL that is already known reuses its resource', function () {
    [$owner, $space] = ownerWithResourcesSpace();
    $known = Resource::factory()->create(['url' => 'https://example.com/known']);

    expect(addResource($owner, $space, ['url' => 'https://example.com/known'])->resource_id)->toBe($known->id);
});

test('the input is checked', function (array $input, string $field) {
    [$owner, $space] = ownerWithResourcesSpace();

    try {
        (new CreateCommunityResource)->create($owner, $space, $input);
        $this->fail('The input was accepted');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
    expect(CommunityResource::query()->count())->toBe(0);
})->with([
    'no URL' => [['level' => 'beginner'], 'url'],
    'not a URL' => [['url' => 'not a url', 'level' => 'beginner'], 'url'],
    'not http' => [['url' => 'ftp://example.com/file', 'level' => 'beginner'], 'url'],
    'no level' => [['url' => 'https://example.com/a'], 'level'],
    'unknown level' => [['url' => 'https://example.com/a', 'level' => 'guru'], 'level'],
    'name too long' => [['url' => 'https://example.com/a', 'level' => 'beginner', 'name' => str_repeat('a', 65)], 'name'],
]);

test('resources can only be added to resources spaces', function () {
    [$owner, $space] = ownerWithResourcesSpace(type: KnowiiSpaceType::Discussion);

    addResource($owner, $space, ['url' => 'https://example.com/a']);
})->throws(BusinessException::class);

test('the posting policy of the space decides who adds resources', function () {
    $c = communityWithMembers();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Resources, 'posting_policy' => KnowiiSpacePostingPolicy::Members]);

    expect(addResource($c['member'], $space, ['url' => 'https://example.com/a'])->curator_id)->toBe($c['member']->profile->id);

    $space->update(['posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    expect(fn () => addResource($c['member'], $space->refresh(), ['url' => 'https://example.com/b']))->toThrow(AuthorizationException::class)
        ->and(fn () => addResource($c['outsider'], $space, ['url' => 'https://example.com/c']))->toThrow(AuthorizationException::class);
});

test('the same URL cannot be added twice to a space', function () {
    [$owner, $space] = ownerWithResourcesSpace();
    addResource($owner, $space, ['url' => 'https://example.com/some-article']);

    addResource($owner, $space, ['url' => 'https://example.com/some-article']);
})->throws(BusinessException::class, 'This resource is already in this space');
