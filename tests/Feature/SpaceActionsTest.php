<?php

use App\Contracts\Spaces\CreatesSpaces;
use App\Contracts\Spaces\DeletesSpaces;
use App\Contracts\Spaces\ReordersSpaces;
use App\Contracts\Spaces\UpdatesSpaces;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
});

function spaceInput(SpaceGroup $group, array $overrides = []): array
{
    return [
        'space_group_cuid' => $group->cuid,
        'name' => 'Announcements',
        'description' => 'News from the team',
        'emoji' => '📣',
        'type' => 'discussion',
        'visibility' => 'public',
        'posting_policy' => 'moderators',
        ...$overrides,
    ];
}

test('admins create spaces of every type at the end of a group', function (string $type) {
    $creator = app(CreatesSpaces::class);

    $first = $creator->create($this->c['admin'], $this->c['community'], spaceInput($this->group, ['type' => $type]));
    $second = $creator->create($this->c['admin'], $this->c['community'], spaceInput($this->group, ['type' => $type, 'name' => 'Second']));

    expect($first->type)->toBe(KnowiiSpaceType::from($type))
        ->and($first->slug)->toBe('announcements')
        ->and($first->visibility)->toBe(KnowiiSpaceVisibility::Public)
        ->and($first->posting_policy)->toBe(KnowiiSpacePostingPolicy::Moderators)
        ->and($first->community_id)->toBe($this->c['community']->id)
        ->and([$first->position, $second->position])->toBe([0, 1]);

    Event::assertDispatched(SpaceTreeChanged::class);
})->with(['discussion', 'chat', 'pages', 'course']);

test('moderators, members and outsiders cannot create spaces', function (string $role) {
    app(CreatesSpaces::class)->create($this->c[$role], $this->c['community'], spaceInput($this->group));
})->with(['moderator', 'member', 'outsider'])->throws(AuthorizationException::class);

test('slugs are unique within a community, not across communities', function () {
    $creator = app(CreatesSpaces::class);
    $first = $creator->create($this->c['owner'], $this->c['community'], spaceInput($this->group));
    $second = $creator->create($this->c['owner'], $this->c['community'], spaceInput($this->group));

    $other = communityWithMembers();
    $otherGroup = SpaceGroup::factory()->create(['community_id' => $other['community']->id]);
    $elsewhere = $creator->create($other['owner'], $other['community'], spaceInput($otherGroup));

    expect($first->slug)->toBe('announcements')
        ->and($second->slug)->toBe('announcements-2')
        ->and($elsewhere->slug)->toBe('announcements');
});

test('a space cannot be created in a group of another community', function () {
    $foreignGroup = SpaceGroup::factory()->create();

    app(CreatesSpaces::class)->create($this->c['owner'], $this->c['community'], spaceInput($foreignGroup));
})->throws(ValidationException::class);

test('space input is validated', function (array $overrides) {
    app(CreatesSpaces::class)->create($this->c['owner'], $this->c['community'], spaceInput($this->group, $overrides));
})->with([
    'unknown type' => [['type' => 'forum']],
    'unknown visibility' => [['visibility' => 'secret']],
    'unknown posting policy' => [['posting_policy' => 'everyone']],
    'name too short' => [['name' => 'a']],
    'emoji with text' => [['emoji' => 'hello']],
    'emoji with markup' => [['emoji' => '<b>']],
    'missing group' => [['space_group_cuid' => null]],
])->throws(ValidationException::class);

test('admins update spaces partially, the type and the slug never change', function () {
    $space = Space::factory()->inGroup($this->group)->create(['name' => 'General', 'type' => KnowiiSpaceType::Chat]);
    $slug = $space->slug;

    $updated = app(UpdatesSpaces::class)->update($this->c['admin'], $space, [
        'name' => 'Lounge',
        'emoji' => '',
        'visibility' => 'public',
        'type' => 'discussion',
    ]);

    expect($updated->name)->toBe('Lounge')
        ->and($updated->emoji)->toBeNull()
        ->and($updated->visibility)->toBe(KnowiiSpaceVisibility::Public)
        ->and($updated->type)->toBe(KnowiiSpaceType::Chat)
        ->and($updated->slug)->toBe($slug)
        ->and($updated->description)->toBe($space->description);
});

test('moving a space puts it at the end of the target group', function () {
    $target = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    Space::factory()->inGroup($target)->create(['position' => 4]);
    $space = Space::factory()->inGroup($this->group)->create();

    $moved = app(UpdatesSpaces::class)->update($this->c['owner'], $space, ['space_group_cuid' => $target->cuid]);

    expect($moved->space_group_id)->toBe($target->id)->and($moved->position)->toBe(5);
});

test('a space cannot move to a group of another community', function () {
    $space = Space::factory()->inGroup($this->group)->create();

    app(UpdatesSpaces::class)->update($this->c['owner'], $space, ['space_group_cuid' => SpaceGroup::factory()->create()->cuid]);
})->throws(ValidationException::class);

test('members cannot update or delete spaces', function (string $operation) {
    $space = Space::factory()->inGroup($this->group)->create();

    $operation === 'update'
        ? app(UpdatesSpaces::class)->update($this->c['member'], $space, ['name' => 'Mine'])
        : app(DeletesSpaces::class)->delete($this->c['member'], $space);
})->with(['update', 'delete'])->throws(AuthorizationException::class);

test('admins delete spaces', function () {
    $space = Space::factory()->inGroup($this->group)->create();

    app(DeletesSpaces::class)->delete($this->c['admin'], $space);

    expect($space->fresh())->toBeNull();
});

test('spaces are reordered within their group', function () {
    [$a, $b] = [
        Space::factory()->inGroup($this->group)->create(['name' => 'A', 'position' => 0]),
        Space::factory()->inGroup($this->group)->create(['name' => 'B', 'position' => 1]),
    ];

    $result = app(ReordersSpaces::class)->reorder($this->c['owner'], $this->group, ['space_cuids' => [$b->cuid, $a->cuid]]);

    expect($result->pluck('name')->all())->toBe(['B', 'A']);
});

test('a reorder of spaces must list every space of the group once', function () {
    $space = Space::factory()->inGroup($this->group)->create();
    Space::factory()->inGroup($this->group)->create();

    app(ReordersSpaces::class)->reorder($this->c['owner'], $this->group, ['space_cuids' => [$space->cuid]]);
})->throws(ValidationException::class);
