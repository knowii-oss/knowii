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
        //
      Schema::table('users', function (Blueprint $table) {
        $table->text('social_link_website')
          ->nullable();

        $table->text('social_link_newsletter')
          ->nullable();

        $table->text('social_link_x')
          ->nullable();

        $table->text('social_link_mastodon')
          ->nullable();

        $table->text('social_link_bluesky')
          ->nullable();

        $table->text('social_link_threads_dot_net')
          ->nullable();

        $table->text('social_link_linkedin')
          ->nullable();

        $table->text('social_link_facebook')
          ->nullable();

        $table->text('social_link_instagram')
          ->nullable();

        $table->text('social_link_reddit')
          ->nullable();

        $table->text('social_link_medium')
          ->nullable();

        $table->text('social_link_substack')
          ->nullable();

        $table->text('social_link_hackernews')
          ->nullable();

        $table->text('social_link_hashnode')
          ->nullable();

        $table->text('social_link_dev_dot_to')
          ->nullable();

        $table->text('social_link_youtube')
          ->nullable();

        $table->text('social_link_tiktok')
          ->nullable();

        $table->text('social_link_twitch')
          ->nullable();

        $table->text('social_link_gumroad')
          ->nullable();

        $table->text('social_link_buymeacoffee')
          ->nullable();

        $table->text('social_link_patreon')
          ->nullable();

        $table->text('social_link_producthunt')
          ->nullable();

        $table->text('social_link_github')
          ->nullable();

        $table->text('social_link_gitlab')
          ->nullable();
      });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
      Schema::table('users', function (Blueprint $table) {
        $table->dropColumn([
          'social_link_x',
          'social_link_website',
          'social_link_newsletter',
          'social_link_mastodon',
          'social_link_bluesky',
          'social_link_threads_dot_net',
          'social_link_linkedin',
          'social_link_facebook',
          'social_link_instagram',
          'social_link_reddit',
          'social_link_medium',
          'social_link_substack',
          'social_link_hackernews',
          'social_link_hashnode',
          'social_link_dev_dot_to',
          'social_link_youtube',
          'social_link_tiktok',
          'social_link_twitch',
          'social_link_gumroad',
          'social_link_buymeacoffee',
          'social_link_patreon',
          'social_link_producthunt',
          'social_link_github',
          'social_link_gitlab',
        ]);
      });
    }
};
