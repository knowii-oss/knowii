<?php

namespace Database\Seeders;

use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Community;
use App\Models\Space;
use Illuminate\Database\Seeder;

/**
 * A space tree like the Knowii Community one, in the "Tests" community of the seeded user.
 */
class SpaceSeeder extends Seeder
{
    public const string BRUNO_SPACE_GROUP_CUID = 'q8kspacegroupstarthere01';

    public const string BRUNO_SPACE_CUID = 'q8kspacewelcome000000001';

    // The cuid of the former "Cool links" resource collection (ADR 0015)
    public const string BRUNO_RESOURCES_SPACE_CUID = 'z40cggggsksggkgskkwocock';

    /**
     * @var array<string, list<array{name: string, emoji: string, type: KnowiiSpaceType, visibility: KnowiiSpaceVisibility, posting_policy: KnowiiSpacePostingPolicy, description: string}>>
     */
    private const array TREE = [
        'Start Here' => [
            ['name' => 'Welcome', 'emoji' => '👋', 'type' => KnowiiSpaceType::Pages, 'visibility' => KnowiiSpaceVisibility::Public, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators, 'description' => 'Start here: how the community works'],
            ['name' => 'Introduce Yourself', 'emoji' => '🙋', 'type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Members, 'posting_policy' => KnowiiSpacePostingPolicy::Members, 'description' => 'Say hello to the other members'],
        ],
        'Community' => [
            ['name' => 'Announcements', 'emoji' => '📣', 'type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Public, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators, 'description' => 'News from the team'],
            ['name' => 'General', 'emoji' => '⚡', 'type' => KnowiiSpaceType::Chat, 'visibility' => KnowiiSpaceVisibility::Members, 'posting_policy' => KnowiiSpacePostingPolicy::Members, 'description' => 'Chat about anything'],
        ],
        'Learn' => [
            ['name' => 'Obsidian Starter Course', 'emoji' => '🎓', 'type' => KnowiiSpaceType::Course, 'visibility' => KnowiiSpaceVisibility::Members, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators, 'description' => 'Learn Obsidian step by step'],
        ],
        'Resources' => [
            ['name' => 'Cool links', 'emoji' => '🔗', 'type' => KnowiiSpaceType::Resources, 'visibility' => KnowiiSpaceVisibility::Members, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators, 'description' => 'A collection of cool links'],
        ],
    ];

    final public function run(): void
    {
        $community = Community::where('name', 'Tests')->first();

        if ($community === null || $community->spaceGroups()->exists()) {
            return;
        }

        $groupPosition = 0;
        foreach (self::TREE as $groupName => $spaces) {
            $group = $community->spaceGroups()->create([
                'name' => $groupName,
                'position' => $groupPosition++,
                // Fixed cuid of the first group: used by Bruno (documentation/api)
                ...($groupName === 'Start Here' ? ['cuid' => self::BRUNO_SPACE_GROUP_CUID] : []),
            ]);

            foreach ($spaces as $position => $space) {
                Space::create([
                    ...$space,
                    'community_id' => $community->id,
                    'space_group_id' => $group->id,
                    'position' => $position,
                    ...($space['name'] === 'Welcome' ? ['cuid' => self::BRUNO_SPACE_CUID] : []),
                    ...($space['name'] === 'Cool links' ? ['cuid' => self::BRUNO_RESOURCES_SPACE_CUID] : []),
                ]);
            }
        }
    }
}
