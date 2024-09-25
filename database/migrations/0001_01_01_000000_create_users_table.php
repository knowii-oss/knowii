<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Fortify;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('cuid');
            $table->string('username')->unique()->index();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            $table->text('two_factor_secret')
                ->after('password')
                ->nullable();
            $table->text('two_factor_recovery_codes')
                ->after('two_factor_secret')
                ->nullable();

            if (Fortify::confirmsTwoFactorAuthentication()) {
                $table->timestamp('two_factor_confirmed_at')
                    ->after('two_factor_recovery_codes')
                    ->nullable();
            }

            $table->rememberToken();
            $table->string('profile_photo_path', 2048)->nullable();
            $table->text('social_link_website')->nullable();
            $table->text('social_link_newsletter')->nullable();
            $table->text('social_link_x')->nullable();
            $table->text('social_link_mastodon')->nullable();
            $table->text('social_link_bluesky')->nullable();
            $table->text('social_link_threads_dot_net')->nullable();
            $table->text('social_link_linkedin')->nullable();
            $table->text('social_link_facebook')->nullable();
            $table->text('social_link_instagram')->nullable();
            $table->text('social_link_reddit')->nullable();
            $table->text('social_link_medium')->nullable();
            $table->text('social_link_substack')->nullable();
            $table->text('social_link_hackernews')->nullable();
            $table->text('social_link_hashnode')->nullable();
            $table->text('social_link_dev_dot_to')->nullable();
            $table->text('social_link_youtube')->nullable();
            $table->text('social_link_tiktok')->nullable();
            $table->text('social_link_twitch')->nullable();
            $table->text('social_link_gumroad')->nullable();
            $table->text('social_link_buymeacoffee')->nullable();
            $table->text('social_link_patreon')->nullable();
            $table->text('social_link_producthunt')->nullable();
            $table->text('social_link_github')->nullable();
            $table->text('social_link_gitlab')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
