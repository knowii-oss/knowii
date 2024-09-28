<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('user_profiles', function (Blueprint $table) {
      $table->id();
      $table->string('cuid');
      $table->unsignedBigInteger('user_id')->nullable()->unique(); // A user profile can exist for someone who has no user account.
      $table->string('name'); // WARNING: This field is a synchronized COPY of the user's name (IF the user exists). That's why there is no foreign key to the users table for this column
      $table->string('profile_photo_path', 2048)->nullable();
      $table->text('bio')->nullable();
      $table->string('location')->nullable();
      $table->string('phone')->nullable();
      $table->string('social_link_website')->nullable();
      $table->string('social_link_newsletter')->nullable();
      $table->string('social_link_x')->nullable();
      $table->string('social_link_mastodon')->nullable();
      $table->string('social_link_bluesky')->nullable();
      $table->string('social_link_threads_dot_net')->nullable();
      $table->string('social_link_linkedin')->nullable();
      $table->string('social_link_facebook')->nullable();
      $table->string('social_link_instagram')->nullable();
      $table->string('social_link_reddit')->nullable();
      $table->string('social_link_medium')->nullable();
      $table->string('social_link_substack')->nullable();
      $table->string('social_link_hackernews')->nullable();
      $table->string('social_link_hashnode')->nullable();
      $table->string('social_link_dev_dot_to')->nullable();
      $table->string('social_link_youtube')->nullable();
      $table->string('social_link_tiktok')->nullable();
      $table->string('social_link_twitch')->nullable();
      $table->string('social_link_gumroad')->nullable();
      $table->string('social_link_buymeacoffee')->nullable();
      $table->string('social_link_patreon')->nullable();
      $table->string('social_link_producthunt')->nullable();
      $table->string('social_link_github')->nullable();
      $table->string('social_link_gitlab')->nullable();
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('user_profiles');
  }
};
