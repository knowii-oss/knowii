<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Visus\Cuid2\Cuid2;

/**
 * Resource collections become spaces of the `resources` type (#1126, ADR 0015).
 *
 * up(): each community with collections gets a "Resources" space group at the end of its navigation, with one
 * `resources` space per collection (same name, description and slug). The resources move to their space, then the
 * collections table is dropped. down() rebuilds the collections from the `resources` spaces: no backup is needed.
 *
 * Plain queries on purpose: the models of today must not shape an old migration.
 */
return new class extends Migration
{
    private const array TYPES_BEFORE = ['discussion', 'chat', 'pages', 'course'];

    private const array TYPES_AFTER = ['discussion', 'chat', 'pages', 'course', 'resources'];

    private const string GROUP_NAME = 'Resources';

    public function up(): void
    {
        self::setSpaceTypes(self::TYPES_AFTER);

        Schema::table('community_resources', static function (Blueprint $table) {
            // Deleting a space deletes its resources (ADR 0012, decision 7)
            $table->foreignId('space_id')->nullable()->after('community_id')->constrained()->cascadeOnDelete();
        });

        $now = now();
        $groups = [];
        $positions = [];

        /** @var Collection<int, object{id: int, cuid: string, community_id: int, name: string, slug: string, description: ?string, created_at: ?string, updated_at: ?string}> $collections */
        $collections = DB::table('community_resource_collections')->orderBy('community_id')->orderBy('name')->get();

        foreach ($collections as $collection) {
            $communityId = $collection->community_id;

            if (! isset($groups[$communityId])) {
                $groups[$communityId] = DB::table('space_groups')->insertGetId([
                    'cuid' => self::cuid(),
                    'community_id' => $communityId,
                    'name' => self::GROUP_NAME,
                    'position' => self::nextGroupPosition($communityId),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $positions[$communityId] = 0;
            }

            $spaceId = DB::table('spaces')->insertGetId([
                // The cuid stays: it is the public identifier of the collection
                'cuid' => $collection->cuid,
                'community_id' => $communityId,
                'space_group_id' => $groups[$communityId],
                'name' => $collection->name,
                'slug' => self::freeSlug($communityId, $collection->slug),
                'description' => $collection->description,
                'emoji' => null,
                'type' => 'resources',
                'visibility' => 'members',
                // Before: only moderators added resources
                'posting_policy' => 'moderators',
                'position' => $positions[$communityId]++,
                'created_at' => $collection->created_at,
                'updated_at' => $collection->updated_at,
            ]);

            DB::table('community_resources')->where('collection_id', $collection->id)->update(['space_id' => $spaceId]);
        }

        Schema::table('community_resources', static function (Blueprint $table) {
            $table->dropUnique(['resource_id', 'community_id', 'collection_id']);
            $table->dropConstrainedForeignId('collection_id');
            $table->foreignId('space_id')->nullable(false)->change();
            // A resource is in a space once
            $table->unique(['space_id', 'resource_id']);
        });

        Schema::drop('community_resource_collections');
    }

    public function down(): void
    {
        Schema::create('community_resource_collections', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique()->index();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('name')->index();
            $table->string('slug')->index();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['community_id', 'slug']);
        });
        DB::statement('CREATE UNIQUE INDEX community_resource_collections_community_name_unique ON community_resource_collections (community_id, lower(name))');

        Schema::table('community_resources', static function (Blueprint $table) {
            $table->foreignId('collection_id')->nullable()->after('community_id')->constrained('community_resource_collections')->cascadeOnDelete();
        });

        /** @var Collection<int, object{id: int, cuid: string, community_id: int, space_group_id: int, name: string, slug: string, description: ?string, created_at: ?string, updated_at: ?string}> $spaces */
        $spaces = DB::table('spaces')->where('type', 'resources')->orderBy('community_id')->orderBy('position')->get();

        foreach ($spaces as $space) {
            $collectionId = DB::table('community_resource_collections')->insertGetId([
                'cuid' => $space->cuid,
                'community_id' => $space->community_id,
                'name' => self::freeCollectionName($space->community_id, $space->name),
                'slug' => $space->slug,
                'description' => $space->description,
                'created_at' => $space->created_at,
                'updated_at' => $space->updated_at,
            ]);

            DB::table('community_resources')->where('space_id', $space->id)->update(['collection_id' => $collectionId]);
        }

        Schema::table('community_resources', static function (Blueprint $table) {
            $table->dropUnique(['space_id', 'resource_id']);
            // Drop the space link before deleting the spaces: the cascade would delete the resources
            $table->dropConstrainedForeignId('space_id');
            $table->foreignId('collection_id')->nullable(false)->change();
            $table->unique(['resource_id', 'community_id', 'collection_id']);
        });

        $groupIds = $spaces->pluck('space_group_id')->unique()->all();
        DB::table('spaces')->where('type', 'resources')->delete();
        // Remove the groups that only held resources spaces
        DB::table('space_groups')->whereIn('id', $groupIds)->whereNotExists(
            fn (Builder $query) => $query->select(DB::raw(1))->from('spaces')->whereColumn('spaces.space_group_id', 'space_groups.id')
        )->delete();

        self::setSpaceTypes(self::TYPES_BEFORE);
    }

    /**
     * Laravel's enum() is a check constraint on PostgreSQL.
     *
     * @param  list<string>  $types
     */
    private static function setSpaceTypes(array $types): void
    {
        $values = implode(', ', array_map(static fn (string $type) => "'{$type}'", $types));

        DB::statement('ALTER TABLE spaces DROP CONSTRAINT IF EXISTS spaces_type_check');
        DB::statement("ALTER TABLE spaces ADD CONSTRAINT spaces_type_check CHECK (type IN ({$values}))");
    }

    /**
     * Space slugs are unique in the community: add a suffix when a space already holds the slug.
     */
    private static function freeSlug(int $communityId, string $slug): string
    {
        $candidate = $slug;
        $suffix = 2;

        while (DB::table('spaces')->where('community_id', $communityId)->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }

    /**
     * Collection names were unique in the community (#925); space names are not.
     */
    private static function freeCollectionName(int $communityId, string $name): string
    {
        $candidate = $name;
        $suffix = 2;

        while (DB::table('community_resource_collections')->where('community_id', $communityId)->whereRaw('lower(name) = ?', [mb_strtolower($candidate)])->exists()) {
            $candidate = $name.' '.$suffix++;
        }

        return $candidate;
    }

    /**
     * The new group goes at the end of the navigation.
     */
    private static function nextGroupPosition(int $communityId): int
    {
        /** @var int|string|null $max */
        $max = DB::table('space_groups')->where('community_id', $communityId)->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }

    private static function cuid(): string
    {
        return (new Cuid2(24))->toString();
    }
};
