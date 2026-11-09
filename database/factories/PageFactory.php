<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        $body = $this->faker->paragraphs(2, true);

        return [
            'cuid' => (new Cuid2)->toString(),
            'title' => rtrim($this->faker->sentence(4), '.'),
            'body' => $body,
            'body_html' => Markdown::render($body),
            'position' => 0,
            'space_id' => Space::factory(),
            'community_id' => fn (array $attributes) => Space::query()->whereKey($attributes['space_id'])->value('community_id'),
            'author_id' => User::factory(),
        ];
    }

    /**
     * Put the page in the given space (and its community).
     */
    final public function inSpace(Space $space): static
    {
        return $this->state(fn () => ['space_id' => $space->id, 'community_id' => $space->community_id]);
    }
}
