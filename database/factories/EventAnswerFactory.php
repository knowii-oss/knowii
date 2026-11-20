<?php

namespace Database\Factories;

use App\Enums\KnowiiEventAnswer;
use App\Models\Event;
use App\Models\EventAnswer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<EventAnswer>
 */
class EventAnswerFactory extends Factory
{
    /**
     * A Yes of a new account to a new event. The tests name the event and the member: `to()` and `of()`.
     *
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        return [
            'cuid' => (new Cuid2)->toString(),
            'event_id' => Event::factory(),
            'community_id' => fn (array $attributes) => Event::query()->whereKey($attributes['event_id'])->value('community_id'),
            'user_id' => User::factory(),
            'answer' => KnowiiEventAnswer::Yes,
        ];
    }

    /**
     * An answer to the given event (and in its community)
     */
    final public function to(Event $event): static
    {
        return $this->state(fn () => ['event_id' => $event->id, 'community_id' => $event->community_id]);
    }

    final public function of(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    final public function yes(): static
    {
        return $this->state(fn () => ['answer' => KnowiiEventAnswer::Yes]);
    }

    final public function replay(): static
    {
        return $this->state(fn () => ['answer' => KnowiiEventAnswer::Replay]);
    }

    final public function no(): static
    {
        return $this->state(fn () => ['answer' => KnowiiEventAnswer::No]);
    }
}
