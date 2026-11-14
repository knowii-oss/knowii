<?php

use App\Enums\KnowiiCommunityVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The owner of a shared community accepts the data processing agreement when it creates the community (GDPR art.
 * 28(3), #1353). Knowii records the date, and the version date of the agreement (the `updated` date of its front
 * matter). A personal community holds nobody else's data: it records nothing.
 *
 * The shared communities that exist get the date of their creation. The version they accepted is not known: it stays
 * empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', static function (Blueprint $table) {
            $table->timestamp('dpa_accepted_at')->nullable();
            $table->date('dpa_version')->nullable();
        });

        DB::table('communities')
            ->where('visibility', '!=', KnowiiCommunityVisibility::Personal->value)
            ->update(['dpa_accepted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('communities', static function (Blueprint $table) {
            $table->dropColumn(['dpa_accepted_at', 'dpa_version']);
        });
    }
};
