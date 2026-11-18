<?php

namespace Database\Factories;

use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiEventState;
use App\Enums\KnowiiSpaceType;
use App\Models\Event;
use App\Models\Space;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * A published event of one hour, a week from now, in Brussels time
     *
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        $body = $this->faker->paragraphs(2, true);
        $start = now()->addWeek()->startOfHour();

        return [
            'cuid' => (new Cuid2)->toString(),
            'title' => rtrim($this->faker->sentence(4), '.'),
            'body' => $body,
            'body_html' => Markdown::render($body),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'time_zone' => 'Europe/Brussels',
            'meeting_url' => 'https://meet.example.com/'.$this->faker->lexify('???-????-???'),
            'place' => null,
            'location_visibility' => KnowiiEventLocationVisibility::Members,
            'state' => KnowiiEventState::Published,
            'sequence' => 0,
            'space_id' => Space::factory()->state(['type' => KnowiiSpaceType::Events]),
            'community_id' => fn (array $attributes) => Space::query()->whereKey($attributes['space_id'])->value('community_id'),
            'host_id' => User::factory(),
        ];
    }

    /**
     * Put the event in the given space (and its community).
     */
    final public function inSpace(Space $space): static
    {
        return $this->state(fn () => ['space_id' => $space->id, 'community_id' => $space->community_id]);
    }

    final public function hostedBy(?User $host): static
    {
        return $this->state(fn () => ['host_id' => $host?->id]);
    }

    final public function draft(): static
    {
        return $this->state(fn () => ['state' => KnowiiEventState::Draft]);
    }

    final public function cancelled(): static
    {
        return $this->state(fn () => ['state' => KnowiiEventState::Cancelled]);
    }

    /**
     * An event that ended a week ago
     */
    final public function past(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subWeek()->startOfHour(), 'ends_at' => now()->subWeek()->startOfHour()->addHour()]);
    }
}
