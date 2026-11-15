<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The widths of the smaller versions of an uploaded image (#1207, ADR 0040), for example [640, 1280]. An empty list
 * means that the image is small and has none. Null means that the versions are not built yet: `uploads:build-sizes`
 * builds them for the uploads that exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploads', static function (Blueprint $table) {
            $table->json('sizes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('uploads', static function (Blueprint $table) {
            $table->dropColumn('sizes');
        });
    }
};
