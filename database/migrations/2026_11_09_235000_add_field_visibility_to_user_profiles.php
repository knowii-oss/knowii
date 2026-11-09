<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which profile fields the other members see (#1076, ADR 0020). Null: the defaults of ProfileFieldVisibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', static function (Blueprint $table) {
            $table->jsonb('field_visibility')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', static function (Blueprint $table) {
            $table->dropColumn('field_visibility');
        });
    }
};
