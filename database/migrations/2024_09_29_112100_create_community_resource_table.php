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

      // If the global resource is deleted, then this is deleted as well
      $table->foreignId('resource_id')->constrained()->cascadeOnDelete();

      // If the community is deleted, then this is deleted as well
      $table->foreignId('community_id')->constrained()->cascadeOnDelete();

      // If the resource collection is deleted, then this is deleted as well
      $table->foreignId('collection_id')->constrained('community_resource_collections')->cascadeOnDelete();

      // If the user profile is deleted, then we keep the resource in the community, but without a link to the user profile
      $table->foreignId('curator_id')->nullable()->constrained('user_profiles')->nullOnDelete();

      $table->boolean('is_featured')->default(false)->index();
      $table->timestamps();
      $table->unique(['resource_id', 'community_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('community_resources');
  }
};
