<?php

use App\Constants;
use App\Contracts\SpaceGroups\CreatesSpaceGroups;
use App\Contracts\SpaceGroups\DeletesSpaceGroups;
use App\Contracts\SpaceGroups\ReordersSpaceGroups;
use App\Contracts\SpaceGroups\UpdatesSpaceGroups;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
});

test('owners and admins create space groups at the end of the navigation', function (string $role) {
    $creator = app(CreatesSpaceGroups::class);

    $first = $creator->create($this->c[$role], $this->c['community'], ['name' => 'Start Here']);
    $second = $creator->create($this->c[$role], $this->c['community'], ['name' => '  Learn  ']);

    expect($first->position)->toBe(0)
        ->and($second->position)->toBe(1)
        ->and($second->name)->toBe('Learn')
        ->and($this->c['community']->spaceGroups()->pluck('name')->all())->toBe(['Start Here', 'Learn']);

    Event::assertDispatched(SpaceTreeChanged::class, fn (SpaceTreeChanged $event) => $event->communityCuid === $this->c['community']->cuid);
})->with(['owner', 'admin']);

test('moderators, members and outsiders cannot create space groups', function (string $role) {
    app(CreatesSpaceGroups::class)->create($this->c[$role], $this->c['community'], ['name' => 'Start Here']);
})->with(['moderator', 'member', 'outsider'])->throws(AuthorizationException::class);

test('a space group needs a valid name', function (string $name) {
    app(CreatesSpaceGroups::class)->create($this->c['owner'], $this->c['community'], ['name' => $name]);
})->with([
    'too short' => 'a',
    'too long' => str_repeat('a', Constants::MAX_LENGTH_SPACE_GROUP_NAME + 1),
])->throws(ValidationException::class);

test('a community has a limited number of space groups', function () {
    SpaceGroup::factory()->count(Constants::MAX_SPACE_GROUPS_PER_COMMUNITY)->create(['community_id' => $this->c['community']->id]);

    app(CreatesSpaceGroups::class)->create($this->c['owner'], $this->c['community'], ['name' => 'One too many']);
})->throws(BusinessException::class);

test('admins rename space groups', function () {
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id, 'name' => 'Old']);

    app(UpdatesSpaceGroups::class)->update($this->c['admin'], $group, ['name' => 'New']);

    expect($group->fresh()->name)->toBe('New');
});

test('members cannot rename space groups', function () {
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);

    app(UpdatesSpaceGroups::class)->update($this->c['member'], $group, ['name' => 'New']);
})->throws(AuthorizationException::class);

test('an empty space group can be deleted', function () {
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);

    app(DeletesSpaceGroups::class)->delete($this->c['owner'], $group);

    expect($group->fresh())->toBeNull();
});

test('a space group with spaces cannot be deleted', function () {
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    Space::factory()->inGroup($group)->create();

    app(DeletesSpaceGroups::class)->delete($this->c['owner'], $group);
})->throws(BusinessException::class);

test('space groups are reordered', function () {
    [$a, $b, $c] = collect(['A', 'B', 'C'])->map(fn (string $name, int $position) => SpaceGroup::factory()->create([
        'community_id' => $this->c['community']->id, 'name' => $name, 'position' => $position,
    ]))->all();

    $result = app(ReordersSpaceGroups::class)->reorder($this->c['admin'], $this->c['community'], [
        'space_group_cuids' => [$c->cuid, $a->cuid, $b->cuid],
    ]);

    expect($result->pluck('name')->all())->toBe(['C', 'A', 'B'])
        ->and($this->c['community']->spaceGroups()->pluck('name')->all())->toBe(['C', 'A', 'B']);
});

test('a reorder must list every space group of the community once', function (callable $cuids) {
    $groups = SpaceGroup::factory()->count(2)->create(['community_id' => $this->c['community']->id]);
    $other = SpaceGroup::factory()->create();

    app(ReordersSpaceGroups::class)->reorder($this->c['owner'], $this->c['community'], ['space_group_cuids' => $cuids($groups, $other)]);
})->with([
    'missing one' => fn () => fn ($groups) => [$groups[0]->cuid],
    'duplicate' => fn () => fn ($groups) => [$groups[0]->cuid, $groups[0]->cuid],
    'from another community' => fn () => fn ($groups, $other) => [$groups[0]->cuid, $other->cuid],
])->throws(ValidationException::class);
