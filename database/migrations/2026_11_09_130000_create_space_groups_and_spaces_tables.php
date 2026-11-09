<?php

use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Space groups and spaces of a community (#1173, ADR 0012).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('space_groups', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            // Deleting a community deletes its space groups
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['community_id', 'position']);
        });

        Schema::create('spaces', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            // Denormalized from the space group: scopes queries and the slug to the community
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            // A space group with spaces cannot be deleted (DeleteSpaceGroup refuses it): cascade only follows a community deletion
            $table->foreignId('space_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->string('emoji', 16)->nullable();
            $table->enum('type', KnowiiSpaceType::toStringArray());
            $table->enum('visibility', KnowiiSpaceVisibility::toStringArray())->default(KnowiiSpaceVisibility::Members->value);
            $table->enum('posting_policy', KnowiiSpacePostingPolicy::toStringArray())->default(KnowiiSpacePostingPolicy::Members->value);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // Slugs are unique within a community (page URLs: /community/{communitySlug}/space/{spaceSlug})
            $table->unique(['community_id', 'slug']);
            $table->index(['space_group_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spaces');
        Schema::dropIfExists('space_groups');
    }
};
