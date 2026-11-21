<?php

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceContentChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Community;
use App\Models\Event as EventModel;
use App\Models\EventAnswer;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

/*
 * The page "My events" (#1515, ADR 0053, "The calendar file and My events"): `/events`, the first 20 events of the open
 * list with their pagination, the scope, and the name of the community on the rows of a member of several communities.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
    Event::fake([SpaceTreeChanged::class, SpaceContentChanged::class]);

    $this->owner = User::factory()->withUserProfile()->create(['name' => 'Olga Owner', 'username' => 'olga']);
    $this->mia = User::factory()->withUserProfile()->create(['name' => 'Mia Member', 'username' => 'mia']);
    $this->first = Community::factory()->create(['owner_id' => $this->owner->id, 'name' => 'Example community']);
    $this->first->users()->attach($this->mia, ['role' => KnowiiCommunityMemberRole::Member]);
    $group = SpaceGroup::factory()->create(['community_id' => $this->first->id, 'name' => 'Example group']);
    $this->office = Space::factory()->inGroup($group)->create(['name' => 'Office hours', 'type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Members]);

    // 25 upcoming events with a Yes of Mia, one past one, and one without an answer
    foreach (range(1, 25) as $i) {
        $event = EventModel::factory()->inSpace($this->office)->hostedBy($this->owner)->create([
            'title' => sprintf('Office hour %02d', $i),
            'starts_at' => CarbonImmutable::parse('2026-11-18T08:00:00Z')->addHours($i),
            'ends_at' => CarbonImmutable::parse('2026-11-18T09:00:00Z')->addHours($i),
        ]);
        EventAnswer::factory()->to($event)->of($this->mia)->yes()->create();
    }
    $past = EventModel::factory()->inSpace($this->office)->hostedBy($this->owner)->create([
        'title' => 'Last week', 'starts_at' => CarbonImmutable::parse('2026-11-03T16:00:00Z'), 'ends_at' => CarbonImmutable::parse('2026-11-03T17:00:00Z'),
    ]);
    EventAnswer::factory()->to($past)->of($this->mia)->replay()->create();
    EventModel::factory()->inSpace($this->office)->hostedBy($this->owner)->create(['title' => 'Not answered']);
});

function mevPage(?User $user, string $query = ''): TestResponse
{
    if ($user === null) {
        app('auth')->forgetGuards();

        return test()->get('/events'.$query);
    }

    return test()->actingAs(User::query()->findOrFail($user->id))->get('/events'.$query);
}

test('the page gives the first 20 upcoming events with their pagination, for a member of one community without the community names', function () {
    mevPage($this->mia)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Events/Index')
        ->where('scope', 'upcoming')
        ->where('showCommunity', false)
        ->has('events.items', 20)
        ->where('events.items.0.title', 'Office hour 01')
        ->where('events.items.19.title', 'Office hour 20')
        ->where('events.items.0.my_answer', 'yes')
        ->where('events.items.0.community.name', 'Example community')
        ->where('events.items.0.space.name', 'Office hours')
        ->where('events.items.0.body', null)
        ->where('events.pagination.totalCount', 25)
        ->where('events.pagination.nextOffset', 20)
        ->where('events.pagination.limit', 20));
});

test('a member of two communities gets the names of the communities', function () {
    $second = Community::factory()->create(['owner_id' => $this->owner->id, 'name' => 'Second community']);
    $second->users()->attach($this->mia, ['role' => KnowiiCommunityMemberRole::Member]);

    mevPage($this->mia)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Events/Index')->where('showCommunity', true));
    // The owner owns both
    mevPage($this->owner)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('showCommunity', true));
});

test('the personal community of the account does not count as a second community while "My events" holds none of its events', function () {
    $personal = Community::factory()->create(['owner_id' => $this->mia->id, 'name' => 'Mia personal', 'visibility' => KnowiiCommunityVisibility::Personal]);
    // An events space with an event that Mia neither hosts nor answered
    $group = SpaceGroup::factory()->create(['community_id' => $personal->id, 'name' => 'Personal group']);
    $space = Space::factory()->inGroup($group)->create(['name' => 'Personal hours', 'type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Members]);
    EventModel::factory()->inSpace($space)->hostedBy($this->owner)->create(['title' => 'Not in my events']);

    mevPage($this->mia)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('showCommunity', false));
});

test('an account with one shared community and an event in its personal community gets the names of the communities', function () {
    $personal = Community::factory()->create(['owner_id' => $this->mia->id, 'name' => 'Mia personal', 'visibility' => KnowiiCommunityVisibility::Personal]);
    $group = SpaceGroup::factory()->create(['community_id' => $personal->id, 'name' => 'Personal group']);
    $space = Space::factory()->inGroup($group)->create(['name' => 'Personal hours', 'type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Members]);
    // A past event that Mia hosts: the names show on "Upcoming" too, where the rows come from one community only
    EventModel::factory()->inSpace($space)->hostedBy($this->mia)->create([
        'title' => 'Mia plans alone', 'starts_at' => CarbonImmutable::parse('2026-11-04T16:00:00Z'), 'ends_at' => CarbonImmutable::parse('2026-11-04T17:00:00Z'),
    ]);

    mevPage($this->mia, '?scope=past')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Events/Index')
        ->where('showCommunity', true)
        ->has('events.items', 2)
        ->where('events.items.0.title', 'Mia plans alone')
        ->where('events.items.0.community.name', 'Mia personal')
        ->where('events.items.1.title', 'Last week')
        ->where('events.items.1.community.name', 'Example community'));
    mevPage($this->mia)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('showCommunity', true));
});

test('"?scope=past" opens the past list, and any other value the upcoming one', function () {
    mevPage($this->mia, '?scope=past')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Events/Index')
        ->where('scope', 'past')
        ->has('events.items', 1)
        ->where('events.items.0.title', 'Last week')
        ->where('events.items.0.my_answer', 'replay')
        ->where('events.pagination.nextOffset', null));

    mevPage($this->mia, '?scope=soon')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('scope', 'upcoming')->has('events.items', 20));
});

test('an account in no community gets the page with empty lists', function () {
    $alone = User::factory()->withUserProfile()->create(['name' => 'Nadia Nobody', 'username' => 'nadia']);

    mevPage($alone)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Events/Index')
        ->where('showCommunity', false)
        ->has('events.items', 0)
        ->where('events.pagination.totalCount', 0));
});

test('a visitor goes to the sign-in page', function () {
    mevPage(null)->assertRedirect(route('login'));
});
