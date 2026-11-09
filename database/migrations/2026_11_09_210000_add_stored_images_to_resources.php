<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resource images are stored by Knowii and served from its own origin, never hotlinked (#1063, ADR 0016): a cover
 * thumbnail and the icon of the site, re-encoded. The paths are on the uploads disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', static function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('cover_image_base64');
            $table->string('favicon_path')->nullable()->after('cover_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('resources', static function (Blueprint $table) {
            $table->dropColumn(['cover_image_path', 'favicon_path']);
        });
    }
};
