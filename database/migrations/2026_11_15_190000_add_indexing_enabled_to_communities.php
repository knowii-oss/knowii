<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Search engines may index the public spaces of a community (#1487, ADR 0044): off by default. The instance setting
 * ROBOTS_ALLOW_INDEXING stays the first gate; this one is the choice of the community.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', static function (Blueprint $table) {
            $table->boolean('indexing_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('communities', static function (Blueprint $table) {
            $table->dropColumn('indexing_enabled');
        });
    }
};
