<?php

namespace Database\Factories;

use App\Enums\KnowiiCaptureSource;
use App\Models\CommunityResource;
use App\Models\ResourceCapture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceCapture>
 */
class ResourceCaptureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $markdown = "# Title\n\n".$this->faker->paragraphs(3, true);

        return [
            'community_resource_id' => CommunityResource::factory(),
            'markdown' => $markdown,
            ...ResourceCapture::measure($markdown),
            'source' => KnowiiCaptureSource::Server,
            'captured_at' => now(),
        ];
    }
}
