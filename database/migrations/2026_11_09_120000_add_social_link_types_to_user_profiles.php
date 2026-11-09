<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New social link types (#1075). WARNING: aligned with Constants::SOCIAL_MEDIA_LINK_PROPERTIES.
     *
     * @var list<string>
     */
    private array $columns = [
        'social_link_wikipedia',
        'social_link_google_scholar',
        'social_link_orcid',
        'social_link_kofi',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn($this->columns);
        });
    }
};
