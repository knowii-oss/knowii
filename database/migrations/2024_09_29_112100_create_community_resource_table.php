<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_resources', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique()->index();

            // WARNING: This field is a foreign key referencing the "slug" field of the resources table
            $table->string('slug')->index();

            // This name overrides the generic platform resource name
            // It enables each community to use a specific name for the resources they add to their collections
            // The global resource will still their own name, either using the name given by the first community
            // that added the resource, or an automatically generated one
            $table->string('name')->index();

            // Same with this field. This is the community-specific resource description
            $table->string('description')->nullable();

            // If the global resource is deleted, then this is deleted as well
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();

            // If the community is deleted, then this is deleted as well
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();

            // If the resource collection is deleted, then this is deleted as well
            $table->foreignId('collection_id')->constrained('community_resource_collections')->cascadeOnDelete();

            // If the resource is a text article, then we will have a direct link to it to simplify data fetching
            $table->foreignId('resource_text_article_id')->nullable()->constrained('resource_text_articles')->cascadeOnDelete();

            // If the user profile is deleted, then we keep the resource in the community, but without a link to the user profile
            $table->foreignId('curator_id')->nullable()->constrained('user_profiles')->nullOnDelete();

            $table->boolean('is_featured')->default(false)->index();
            $table->timestamps();

            // A given resource can only exist once within a given community and resource collection
            $table->unique(['resource_id', 'community_id', 'collection_id']);

            $table->foreign('slug')->references('slug')->on('resources')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_resources');
    }
};
