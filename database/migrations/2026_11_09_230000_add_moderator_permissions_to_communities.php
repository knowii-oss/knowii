<?php

use App\Enums\KnowiiModeratorPermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rights of the moderators of each community, switched by the owner and the admins (#1240, ADR 0017).
 * Existing communities get the defaults: today's behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', static function (Blueprint $table) {
            $table->jsonb('moderator_permissions')->default(json_encode(KnowiiModeratorPermission::defaults(), JSON_THROW_ON_ERROR));
        });
    }

    public function down(): void
    {
        Schema::table('communities', static function (Blueprint $table) {
            $table->dropColumn('moderator_permissions');
        });
    }
};
