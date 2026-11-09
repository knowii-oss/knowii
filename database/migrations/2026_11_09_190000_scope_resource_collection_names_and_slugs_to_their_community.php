<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resource collection names are unique in their community, case-insensitive (#925), and slugs are unique in their
 * community instead of in the whole table (rules, Data 4). No data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_resource_collections', static function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['community_id', 'slug']);
        });

        DB::statement('CREATE UNIQUE INDEX community_resource_collections_community_name_unique ON community_resource_collections (community_id, lower(name))');
    }

    /**
     * Fails when two communities hold the same slug: rename one of them first.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX community_resource_collections_community_name_unique');

        Schema::table('community_resource_collections', static function (Blueprint $table) {
            $table->dropUnique(['community_id', 'slug']);
            $table->unique('slug');
        });
    }
};
