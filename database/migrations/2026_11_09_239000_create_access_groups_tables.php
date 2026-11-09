<?php

use App\Enums\KnowiiSpaceVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Access groups (#1241, ADR 0025): named groups of members that open the spaces with the "groups" visibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_groups', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique(['community_id', 'name']);
        });

        Schema::create('access_group_members', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['access_group_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('access_group_space', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_group_id')->constrained()->cascadeOnDelete();

            $table->unique(['space_id', 'access_group_id']);
            $table->index('access_group_id');
        });

        // The enum column of Laravel is a CHECK constraint on PostgreSQL: allow the new value
        DB::statement('ALTER TABLE spaces DROP CONSTRAINT IF EXISTS spaces_visibility_check');
        DB::statement("ALTER TABLE spaces ADD CONSTRAINT spaces_visibility_check CHECK (visibility IN ('".implode("','", KnowiiSpaceVisibility::toStringArray())."'))");
    }

    public function down(): void
    {
        DB::table('spaces')->where('visibility', 'groups')->update(['visibility' => 'members']);
        DB::statement('ALTER TABLE spaces DROP CONSTRAINT IF EXISTS spaces_visibility_check');
        DB::statement("ALTER TABLE spaces ADD CONSTRAINT spaces_visibility_check CHECK (visibility IN ('public','members'))");

        Schema::dropIfExists('access_group_space');
        Schema::dropIfExists('access_group_members');
        Schema::dropIfExists('access_groups');
    }
};
